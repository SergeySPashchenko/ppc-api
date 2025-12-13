<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Expense;
use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Optimized service for importing and syncing expenses.
 * - Creates Product/ExpenseType if missing
 * - Updates sync_state incrementally
 * - Processes in chunks for memory efficiency
 */
final class OptimizedExpenseImportService
{
    public function __construct(
        private readonly ProductSyncService $productService,
        private readonly ExpenseTypeSyncService $expenseTypeService,
    ) {}

    /**
     * Import expenses incrementally (only new/changed).
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importIncremental(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $syncState = SyncState::getOrCreateFor('expenses');
        $lastDate = $syncState->last_expense_date;
        $lastId = $syncState->last_external_expense_id;

        $repository = app(OptimizedExternalRepository::class);
        $processedCount = 0;

        try {
            foreach ($repository->getExpensesIncremental($lastDate, $lastId) as $expenseData) {
                try {
                    // Don't wrap in transaction - sync services handle their own
                    $result = $this->importSingleExpense($expenseData);
                    $stats[$result]++;
                    $processedCount++;

                    // Update sync state every 100 records for checkpoint
                    if ($processedCount % 100 === 0) {
                        $this->updateSyncState($expenseData);
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Failed to import expense', [
                        'id' => $expenseData['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Final sync state update
            if ($processedCount > 0) {
                $syncState->refresh();
            }
        } catch (\Exception $e) {
            Log::error('Incremental expense import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Import expenses for date range with windowing.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importByDateRange(Carbon $from, Carbon $to): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $repository = app(OptimizedExternalRepository::class);
        $processedCount = 0;
        $lastProcessedDate = null;
        $lastProcessedId = null;

        try {
            foreach ($repository->getExpensesByDateRange($from, $to) as $expenseData) {
                try {
                    DB::beginTransaction();

                    $result = $this->importSingleExpense($expenseData);
                    $stats[$result]++;
                    $processedCount++;
                    $lastProcessedDate = Carbon::parse($expenseData['ExpenseDate']);
                    $lastProcessedId = (int) $expenseData['id'];

                    // Checkpoint every 100 records
                    if ($processedCount % 100 === 0) {
                        $this->updateSyncStateFromData($lastProcessedDate, $lastProcessedId);
                        DB::commit();
                        DB::beginTransaction();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errors']++;
                    Log::error('Failed to import expense', [
                        'id' => $expenseData['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Final sync state update
            if ($lastProcessedDate !== null) {
                $this->updateSyncStateFromData($lastProcessedDate, $lastProcessedId);
            }
        } catch (\Exception $e) {
            Log::error('Date range expense import failed', [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Import last N expenses.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function importLast(int $limit): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $repository = app(OptimizedExternalRepository::class);
        $expenses = $repository->getLastExpenses($limit);

        foreach ($expenses as $expenseData) {
            try {
                // Don't wrap in transaction - sync services handle their own transactions
                // This allows ExpenseType/Product to be committed before Expense creation
                $result = $this->importSingleExpense($expenseData);
                $stats[$result]++;
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Failed to import expense', [
                    'id' => $expenseData['id'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Import single expense.
     *
     * @param  array<string, mixed>  $expenseData
     * @return 'created'|'updated'|'skipped'
     */
    private function importSingleExpense(array $expenseData): string
    {
        $externalId = $expenseData['id'] ?? null;
        $productId = $expenseData['ProductID'] ?? null;
        $expenseTypeId = $expenseData['ExpenseID'] ?? null;

        if ($externalId === null) {
            throw new \InvalidArgumentException('Expense id is required');
        }

        // Sync ExpenseType FIRST (create if missing) - needed for foreign key
        if ($expenseTypeId !== null) {
            try {
                $expenseType = $this->expenseTypeService->syncExpenseType(
                    $expenseTypeId,
                    $expenseData['ExpenseTypeName'] ?? null,
                );

                if ($expenseType === null) {
                    Log::warning('Failed to sync expense type for expense', [
                        'expense_id' => $externalId,
                        'ExpenseID' => $expenseTypeId,
                    ]);

                    return 'skipped';
                }
            } catch (\Exception $e) {
                Log::error('Exception syncing expense type for expense', [
                    'expense_id' => $externalId,
                    'ExpenseID' => $expenseTypeId,
                    'error' => $e->getMessage(),
                ]);

                return 'skipped';
            }
        }

        // Sync Product (create if missing)
        if ($productId !== null) {
            try {
                $product = $this->productService->syncProduct([
                    'ProductID' => $productId,
                    'Product' => $expenseData['ProductName'] ?? "Product {$productId}",
                    'Brand' => $expenseData['ProductBrand'] ?? null,
                    'newSystem' => false,
                    'Visible' => false,
                    'flyer' => 0,
                ]);

                if ($product === null) {
                    Log::warning('Failed to sync product for expense', [
                        'expense_id' => $externalId,
                        'ProductID' => $productId,
                    ]);

                    return 'skipped';
                }
            } catch (\Exception $e) {
                Log::error('Exception syncing product for expense', [
                    'expense_id' => $externalId,
                    'ProductID' => $productId,
                    'error' => $e->getMessage(),
                ]);

                return 'skipped';
            }
        }

        // Find or create expense (wrap in transaction for atomicity)
        $expenseDate = Carbon::parse($expenseData['ExpenseDate'])->startOfDay();

        return DB::transaction(function () use ($expenseDate, $expenseData, $productId, $expenseTypeId) {
            $expense = Expense::query()
                ->where('ExpenseDate', $expenseDate)
                ->where('ProductID', $productId)
                ->where('ExpenseID', $expenseTypeId)
                ->first();

            $isNew = $expense === null;

            if ($isNew) {
                $expense = Expense::create([
                    'ExpenseDate' => $expenseDate,
                    'Expense' => $expenseData['Expense'] ?? 0,
                    'ProductID' => $productId,
                    'ExpenseID' => $expenseTypeId,
                ]);
                Log::info('Created new expense', [
                    'expense_id' => $expense->id,
                    'ExpenseDate' => $expenseDate->format('Y-m-d'),
                ]);
            } else {
                $updated = $this->updateExpenseIfChanged($expense, $expenseData, $expenseDate);
                if (! $updated) {
                    return 'skipped';
                }
                Log::info('Updated expense', [
                    'expense_id' => $expense->id,
                    'ExpenseDate' => $expenseDate->format('Y-m-d'),
                ]);
            }

            return $isNew ? 'created' : 'updated';
        });
    }

    /**
     * Update expense if data changed.
     *
     * @param  array<string, mixed>  $expenseData
     */
    private function updateExpenseIfChanged(Expense $expense, array $expenseData, Carbon $expenseDate): bool
    {
        $changed = false;

        $fields = [
            'ExpenseDate' => $expenseDate,
            'Expense' => $expenseData['Expense'] ?? 0,
            'ProductID' => $expenseData['ProductID'] ?? null,
            'ExpenseID' => $expenseData['ExpenseID'] ?? null,
        ];

        foreach ($fields as $field => $value) {
            if ($value != $expense->$field) {
                $expense->$field = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $expense->save();
        }

        return $changed;
    }

    /**
     * Update sync state from expense data.
     *
     * @param  array<string, mixed>  $expenseData
     */
    private function updateSyncState(array $expenseData): void
    {
        $expenseDate = Carbon::parse($expenseData['ExpenseDate']);
        $expenseId = (int) $expenseData['id'];
        $this->updateSyncStateFromData($expenseDate, $expenseId);
    }

    /**
     * Update sync state from date and ID.
     */
    private function updateSyncStateFromData(Carbon $expenseDate, ?int $expenseId): void
    {
        $syncState = SyncState::getOrCreateFor('expenses');
        $syncState->updateExpenseSync($expenseDate->format('Y-m-d'), $expenseId);
    }
}
