<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\DateRangeResolver;
use App\Services\Import\ExpenseImportService;
use App\Services\Import\ExternalRepository;
use App\Services\Import\OrderImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class ImportSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:sync
                            {--date= : Single date to import (Y-m-d format)}
                            {--from= : Start date for range (Y-m-d format)}
                            {--to= : End date for range (Y-m-d format)}
                            {--last-days= : Import last N days}
                            {--limit= : Import last N records (by count, not date)}
                            {--only= : Import only specific domain (expenses,orders)}
                            {--chunk=100 : Number of records to process per chunk}
                            {--incremental : Use incremental import (only new/changed records)}
                            {--optimized : Use optimized import with auto-creation of missing entities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import and sync data from external MySQL database';

    public function __construct(
        private readonly ExternalRepository $externalRepository,
        private readonly DateRangeResolver $dateRangeResolver,
        private readonly ExpenseImportService $expenseService,
        private readonly OrderImportService $orderService,
    ) {
        parent::__construct();
    }

    /**
     * Get optimized services if --optimized flag is set.
     */
    private function getOptimizedServices(): array
    {
        return [
            'expense' => app(\App\Services\Import\OptimizedExpenseImportService::class),
            'order' => app(\App\Services\Import\OptimizedOrderImportService::class),
            'repository' => app(\App\Services\Import\OptimizedExternalRepository::class),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting data import from external database...');

        // Test connection
        if (! $this->externalRepository->testConnection()) {
            $this->error('Failed to connect to external database. Please check your configuration.');

            return Command::FAILURE;
        }

        $this->info('✓ External database connection successful');

        $only = $this->option('only');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $incremental = $this->option('incremental');
        $optimized = $this->option('optimized');

        // Use optimized mode if flag is set
        if ($optimized) {
            return $this->handleOptimizedImport($only, $limit, $incremental);
        }

        // Legacy mode (backward compatibility)
        if ($limit === null) {
            [$from, $to] = $this->dateRangeResolver->resolve(
                $this->option('date'),
                $this->option('from'),
                $this->option('to'),
                $this->option('last-days') ? (int) $this->option('last-days') : null,
            );
            $this->info(sprintf('Date range: %s', $this->dateRangeResolver->formatRange($from, $to)));
        } else {
            $to = Carbon::now();
            $from = $to->copy()->subYears(10);
            $this->info(sprintf('Importing last %d records (by count, not date)...', $limit));
        }

        $chunkSize = (int) $this->option('chunk');

        $totalStats = [
            'expenses' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'orders' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        // Import expenses
        if ($only === null || $only === 'expenses') {
            $this->info('Importing expenses...');
            $expensesStats = $this->importExpenses($from, $to, $chunkSize, $limit);
            $totalStats['expenses'] = $expensesStats;
            $this->displayStats('Expenses', $expensesStats);
        }

        // Import orders
        if ($only === null || $only === 'orders') {
            $this->info('Importing orders...');
            $ordersStats = $this->importOrders($from, $to, $chunkSize, $limit);
            $totalStats['orders'] = $ordersStats;
            $this->displayStats('Orders', $ordersStats);
        }

        // Summary
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->displayStats('Expenses', $totalStats['expenses']);
        $this->displayStats('Orders', $totalStats['orders']);

        $this->info('Import completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Import expenses with chunking.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    private function importExpenses(Carbon $from, Carbon $to, int $chunkSize, ?int $limit = null): array
    {
        $expenses = $this->externalRepository->getExpenses($from, $to, $limit);
        $total = count($expenses);

        if ($total === 0) {
            $this->warn('No expenses found for the specified date range.');

            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $this->info(sprintf('Found %d expenses to import', $total));

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $chunks = array_chunk($expenses, $chunkSize);
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $chunkStats = $this->expenseService->import($chunk);
            foreach ($chunkStats as $key => $value) {
                $stats[$key] += $value;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Import orders with chunking.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    private function importOrders(Carbon $from, Carbon $to, int $chunkSize, ?int $limit = null): array
    {
        $orders = $this->externalRepository->getOrders($from, $to, $limit);
        $total = count($orders);

        if ($total === 0) {
            $this->warn('No orders found for the specified date range.');

            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $this->info(sprintf('Found %d orders to import', $total));

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $chunks = array_chunk($orders, $chunkSize);
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $chunkStats = $this->orderService->import($chunk);
            foreach ($chunkStats as $key => $value) {
                $stats[$key] += $value;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Handle optimized import mode.
     */
    private function handleOptimizedImport(?string $only, ?int $limit, bool $incremental): int
    {
        $services = $this->getOptimizedServices();
        $totalStats = [
            'expenses' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'orders' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        if ($incremental) {
            $this->info('Using incremental import mode (only new/changed records)...');

            if ($only === null || $only === 'expenses') {
                $this->info('Importing expenses incrementally...');
                $stats = $services['expense']->importIncremental();
                $totalStats['expenses'] = $stats;
                $this->displayStats('Expenses', $stats);
            }

            if ($only === null || $only === 'orders') {
                $this->info('Importing orders incrementally...');
                $stats = $services['order']->importIncremental();
                $totalStats['orders'] = $stats;
                $this->displayStats('Orders', $stats);
            }
        } elseif ($limit !== null) {
            $this->info(sprintf('Importing last %d records (optimized mode)...', $limit));

            if ($only === null || $only === 'expenses') {
                $this->info('Importing expenses...');
                $stats = $services['expense']->importLast($limit);
                $totalStats['expenses'] = $stats;
                $this->displayStats('Expenses', $stats);
            }

            if ($only === null || $only === 'orders') {
                $this->info('Importing orders...');
                $stats = $services['order']->importLast($limit);
                $totalStats['orders'] = $stats;
                $this->displayStats('Orders', $stats);
            }
        } else {
            [$from, $to] = $this->dateRangeResolver->resolve(
                $this->option('date'),
                $this->option('from'),
                $this->option('to'),
                $this->option('last-days') ? (int) $this->option('last-days') : null,
            );
            $this->info(sprintf('Date range: %s (optimized mode)', $this->dateRangeResolver->formatRange($from, $to)));

            if ($only === null || $only === 'expenses') {
                $this->info('Importing expenses...');
                $stats = $services['expense']->importByDateRange($from, $to);
                $totalStats['expenses'] = $stats;
                $this->displayStats('Expenses', $stats);
            }

            if ($only === null || $only === 'orders') {
                $this->info('Importing orders...');
                $stats = $services['order']->importByDateRange($from, $to);
                $totalStats['orders'] = $stats;
                $this->displayStats('Orders', $stats);
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->displayStats('Expenses', $totalStats['expenses']);
        $this->displayStats('Orders', $totalStats['orders']);

        $this->info('Import completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Display import statistics.
     *
     * @param  array{created: int, updated: int, skipped: int, errors: int}  $stats
     */
    private function displayStats(string $domain, array $stats): void
    {
        $this->line(sprintf(
            '%s: %d created, %d updated, %d skipped, %d errors',
            $domain,
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors'],
        ));
    }
}
