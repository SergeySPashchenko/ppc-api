<?php

declare(strict_types=1);

namespace App\Services\Import;

use Carbon\Carbon;

/**
 * Central date range resolver for import operations.
 * Supports single day, last N days, and arbitrary date ranges.
 */
final class DateRangeResolver
{
    /**
     * Resolve date range from various input formats.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolve(?string $date = null, ?string $from = null, ?string $to = null, ?int $lastDays = null): array
    {
        // Single date
        if ($date !== null) {
            $dateObj = Carbon::parse($date)->startOfDay();

            return [$dateObj, $dateObj->copy()->endOfDay()];
        }

        // Date range
        if ($from !== null && $to !== null) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        // Last N days
        if ($lastDays !== null) {
            return [
                Carbon::now()->subDays($lastDays)->startOfDay(),
                Carbon::now()->endOfDay(),
            ];
        }

        // Default: last 7 days
        return [
            Carbon::now()->subDays(7)->startOfDay(),
            Carbon::now()->endOfDay(),
        ];
    }

    /**
     * Format date range for display.
     */
    public function formatRange(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('Y-m-d');
        }

        return sprintf('%s to %s', $from->format('Y-m-d'), $to->format('Y-m-d'));
    }
}
