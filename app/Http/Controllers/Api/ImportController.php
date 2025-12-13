<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Import\DateRangeResolver;
use App\Services\Import\ExpenseImportService;
use App\Services\Import\ExternalRepository;
use App\Services\Import\OrderImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ImportController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly ExternalRepository $externalRepository,
        private readonly DateRangeResolver $dateRangeResolver,
        private readonly ExpenseImportService $expenseService,
        private readonly OrderImportService $orderService,
    ) {}

    /**
     * Trigger data import from external database.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'from' => 'nullable|date|required_with:to',
            'to' => 'nullable|date|required_with:from',
            'last_days' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'only' => 'nullable|in:expenses,orders',
            'chunk' => 'nullable|integer|min:1|max:1000',
        ]);

        // Test connection first
        if (! $this->externalRepository->testConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to external database. Please check your configuration.',
            ], 500);
        }

        // Resolve date range
        [$from, $to] = $this->dateRangeResolver->resolve(
            $request->input('date'),
            $request->input('from'),
            $request->input('to'),
            $request->input('last_days'),
        );

        $only = $request->input('only');
        $chunkSize = (int) ($request->input('chunk', 100));
        $limit = $request->input('limit') ? (int) $request->input('limit') : null;

        $stats = [
            'expenses' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'orders' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        try {
            // Import expenses
            if ($only === null || $only === 'expenses') {
                $expenses = $this->externalRepository->getExpenses($from, $to, $limit);
                if (! empty($expenses)) {
                    $chunks = array_chunk($expenses, $chunkSize);
                    foreach ($chunks as $chunk) {
                        $chunkStats = $this->expenseService->import($chunk);
                        foreach ($chunkStats as $key => $value) {
                            $stats['expenses'][$key] += $value;
                        }
                    }
                }
            }

            // Import orders
            if ($only === null || $only === 'orders') {
                $orders = $this->externalRepository->getOrders($from, $to, $limit);
                if (! empty($orders)) {
                    $chunks = array_chunk($orders, $chunkSize);
                    foreach ($chunks as $chunk) {
                        $chunkStats = $this->orderService->import($chunk);
                        foreach ($chunkStats as $key => $value) {
                            $stats['orders'][$key] += $value;
                        }
                    }
                }
            }

            Log::info('Import sync completed via API', [
                'date_range' => $this->dateRangeResolver->formatRange($from, $to),
                'stats' => $stats,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully',
                'date_range' => [
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ],
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Import sync failed via API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connection to external database.
     */
    public function testConnection(): JsonResponse
    {
        $connected = $this->externalRepository->testConnection();

        return response()->json([
            'connected' => $connected,
            'message' => $connected
                ? 'Successfully connected to external database'
                : 'Failed to connect to external database',
        ], $connected ? 200 : 500);
    }
}
