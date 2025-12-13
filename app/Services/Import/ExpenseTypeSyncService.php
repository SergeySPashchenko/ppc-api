<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Expensetype;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing expense types from external data.
 * Creates expense types if they don't exist, updates if changed.
 */
final class ExpenseTypeSyncService
{
    /**
     * Sync expense type from external data.
     * Creates expense type if doesn't exist, updates if changed.
     */
    public function syncExpenseType(?int $expenseTypeId, ?string $expenseTypeName): ?Expensetype
    {
        if ($expenseTypeId === null) {
            return null;
        }

        $expenseType = Expensetype::query()
            ->where('ExpenseID', $expenseTypeId)
            ->first();

        if ($expenseType === null) {
            $expenseType = Expensetype::create([
                'ExpenseID' => $expenseTypeId,
                'Name' => $expenseTypeName ?? "Expense Type {$expenseTypeId}",
            ]);
            Log::info('Created new expense type', ['ExpenseID' => $expenseTypeId]);
        } else {
            // Check if name changed
            if ($expenseTypeName !== null && $expenseType->Name !== $expenseTypeName) {
                $expenseType->Name = $expenseTypeName;
                $expenseType->save();
                Log::info('Updated expense type', ['ExpenseID' => $expenseTypeId]);
            }
        }

        return $expenseType;
    }
}
