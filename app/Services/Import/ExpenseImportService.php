<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Expense;
use App\Models\Expensetype;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing and syncing expenses from external database.
 * Handles idempotent updates and product/expense type verification.
 */
final class ExpenseImportService
{
    /**
     * Import expenses from external data.
     *
     * @param  array<int, array<string, mixed>>  $expensesData
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function import(array $expensesData): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($expensesData as $expenseData) {
            try {
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

        // Verify Product exists
        if ($productId !== null) {
            $product = Product::query()->where('ProductID', $productId)->first();
            if ($product === null) {
                Log::warning('Expense references non-existent Product', [
                    'expense_id' => $externalId,
                    'ProductID' => $productId,
                ]);

                return 'skipped';
            }
        }

        // Verify ExpenseType exists
        if ($expenseTypeId !== null) {
            $expenseType = Expensetype::query()->where('ExpenseID', $expenseTypeId)->first();
            if ($expenseType === null) {
                Log::warning('Expense references non-existent ExpenseType', [
                    'expense_id' => $externalId,
                    'ExpenseID' => $expenseTypeId,
                ]);

                return 'skipped';
            }
        }

        // Find or create expense
        // Note: External DB uses 'id' as primary key, but we need to map it
        // Since we don't have a direct mapping, we'll use ExpenseDate + ProductID + ExpenseID as unique identifier
        $expenseDate = $this->parseExpenseDate($expenseData['ExpenseDate'] ?? null);

        $expense = Expense::query()
            ->where('ExpenseDate', $expenseDate)
            ->where('ProductID', $productId)
            ->where('ExpenseID', $expenseTypeId)
            ->first();

        $isNew = $expense === null;

        if ($isNew) {
            $expense = $this->createExpense($expenseData, $expenseDate);
            Log::info('Created new expense', [
                'expense_id' => $expense->id,
                'ExpenseDate' => $expenseDate?->format('Y-m-d'),
            ]);
        } else {
            $updated = $this->updateExpenseIfChanged($expense, $expenseData, $expenseDate);
            if ($updated) {
                Log::info('Updated expense', [
                    'expense_id' => $expense->id,
                    'ExpenseDate' => $expenseDate?->format('Y-m-d'),
                ]);
            }
        }

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Create new expense.
     *
     * @param  array<string, mixed>  $expenseData
     */
    private function createExpense(array $expenseData, ?Carbon $expenseDate): Expense
    {
        return Expense::create([
            'ExpenseDate' => $expenseDate,
            'Expense' => $expenseData['Expense'] ?? 0,
            'ProductID' => $expenseData['ProductID'] ?? null,
            'ExpenseID' => $expenseData['ExpenseID'] ?? null,
        ]);
    }

    /**
     * Update expense if data has changed.
     *
     * @param  array<string, mixed>  $expenseData
     */
    private function updateExpenseIfChanged(Expense $expense, array $expenseData, ?Carbon $expenseDate): bool
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
     * Parse ExpenseDate string to Carbon date.
     */
    private function parseExpenseDate(mixed $date): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Exception $e) {
            Log::warning('Failed to parse ExpenseDate', ['date' => $date]);

            return null;
        }
    }
}
