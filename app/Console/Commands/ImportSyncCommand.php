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
                            {--only= : Import only specific domain (expenses,orders)}
                            {--chunk=100 : Number of records to process per chunk}';

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

        // Resolve date range
        [$from, $to] = $this->dateRangeResolver->resolve(
            $this->option('date'),
            $this->option('from'),
            $this->option('to'),
            $this->option('last-days') ? (int) $this->option('last-days') : null,
        );

        $this->info(sprintf('Date range: %s', $this->dateRangeResolver->formatRange($from, $to)));

        $only = $this->option('only');
        $chunkSize = (int) $this->option('chunk');

        $totalStats = [
            'expenses' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'orders' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        // Import expenses
        if ($only === null || $only === 'expenses') {
            $this->info('Importing expenses...');
            $expensesStats = $this->importExpenses($from, $to, $chunkSize);
            $totalStats['expenses'] = $expensesStats;
            $this->displayStats('Expenses', $expensesStats);
        }

        // Import orders
        if ($only === null || $only === 'orders') {
            $this->info('Importing orders...');
            $ordersStats = $this->importOrders($from, $to, $chunkSize);
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
    private function importExpenses(Carbon $from, Carbon $to, int $chunkSize): array
    {
        $expenses = $this->externalRepository->getExpenses($from, $to);
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
    private function importOrders(Carbon $from, Carbon $to, int $chunkSize): array
    {
        $orders = $this->externalRepository->getOrders($from, $to);
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
