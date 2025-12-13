<?php

namespace App\Filament\Pages;

use App\Services\Import\DateRangeResolver;
use App\Services\Import\ExpenseImportService;
use App\Services\Import\ExternalRepository;
use App\Services\Import\OrderImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class ImportSync extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected string $view = 'filament.pages.import-sync';

    protected static ?string $navigationLabel = 'Import Sync';

    protected static ?string $title = 'Data Import & Sync';

    protected static ?int $navigationSort = 100;

    public ?string $date = null;

    public ?string $from = null;

    public ?string $to = null;

    public ?int $lastDays = null;

    public ?string $only = null;

    public int $chunk = 100;

    public bool $testConnectionFirst = true;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Connection')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->action(function () {
                    $repository = app(ExternalRepository::class);
                    $connected = $repository->testConnection();

                    if ($connected) {
                        Notification::make()
                            ->title('Connection Successful')
                            ->success()
                            ->body('Successfully connected to external database')
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection Failed')
                            ->danger()
                            ->body('Failed to connect to external database. Please check your configuration.')
                            ->send();
                    }
                }),
            Action::make('import')
                ->label('Start Import')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    DatePicker::make('date')
                        ->label('Single Date')
                        ->helperText('Import data for a specific date'),
                    DatePicker::make('from')
                        ->label('From Date')
                        ->helperText('Start date for date range')
                        ->required(fn ($get) => ! empty($get('to')))
                        ->visible(fn ($get) => empty($get('date')) && empty($get('lastDays'))),
                    DatePicker::make('to')
                        ->label('To Date')
                        ->helperText('End date for date range')
                        ->required(fn ($get) => ! empty($get('from')))
                        ->visible(fn ($get) => empty($get('date')) && empty($get('lastDays'))),
                    TextInput::make('lastDays')
                        ->label('Last N Days')
                        ->numeric()
                        ->helperText('Import data for the last N days')
                        ->visible(fn ($get) => empty($get('date')) && empty($get('from')) && empty($get('limit'))),
                    TextInput::make('limit')
                        ->label('Last N Records')
                        ->numeric()
                        ->helperText('Import last N records by count (not by date)')
                        ->visible(fn ($get) => empty($get('date')) && empty($get('from')) && empty($get('lastDays'))),
                    Select::make('only')
                        ->label('Import Only')
                        ->options([
                            'expenses' => 'Expenses Only',
                            'orders' => 'Orders Only',
                        ])
                        ->placeholder('All (Expenses + Orders)'),
                    TextInput::make('chunk')
                        ->label('Chunk Size')
                        ->numeric()
                        ->default(100)
                        ->helperText('Number of records to process per chunk'),
                    Toggle::make('testConnectionFirst')
                        ->label('Test Connection First')
                        ->default(true)
                        ->helperText('Verify external database connection before importing'),
                ])
                ->action(function (array $data) {
                    $repository = app(ExternalRepository::class);
                    $dateResolver = app(DateRangeResolver::class);
                    $expenseService = app(ExpenseImportService::class);
                    $orderService = app(OrderImportService::class);

                    // Test connection if requested
                    if ($data['testConnectionFirst'] ?? true) {
                        if (! $repository->testConnection()) {
                            Notification::make()
                                ->title('Connection Failed')
                                ->danger()
                                ->body('Failed to connect to external database. Import cancelled.')
                                ->send();

                            return;
                        }
                    }

                    try {
                        // Resolve date range (if limit not specified, use date range)
                        $limit = isset($data['limit']) ? (int) $data['limit'] : null;
                        if ($limit === null) {
                            [$from, $to] = $dateResolver->resolve(
                                $data['date'] ?? null,
                                $data['from'] ?? null,
                                $data['to'] ?? null,
                                isset($data['lastDays']) ? (int) $data['lastDays'] : null,
                            );
                        } else {
                            // For limit mode, use a wide date range to get latest records
                            $to = \Carbon\Carbon::now();
                            $from = $to->copy()->subYears(10); // Wide range to get latest records
                        }

                        $only = $data['only'] ?? null;
                        $chunkSize = (int) ($data['chunk'] ?? 100);

                        $stats = [
                            'expenses' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
                            'orders' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
                        ];

                        // Import expenses
                        if ($only === null || $only === 'expenses') {
                            $expenses = $repository->getExpenses($from, $to, $limit);
                            if (! empty($expenses)) {
                                $chunks = array_chunk($expenses, $chunkSize);
                                foreach ($chunks as $chunk) {
                                    $chunkStats = $expenseService->import($chunk);
                                    foreach ($chunkStats as $key => $value) {
                                        $stats['expenses'][$key] += $value;
                                    }
                                }
                            }
                        }

                        // Import orders
                        if ($only === null || $only === 'orders') {
                            $orders = $repository->getOrders($from, $to, $limit);
                            if (! empty($orders)) {
                                $chunks = array_chunk($orders, $chunkSize);
                                foreach ($chunks as $chunk) {
                                    $chunkStats = $orderService->import($chunk);
                                    foreach ($chunkStats as $key => $value) {
                                        $stats['orders'][$key] += $value;
                                    }
                                }
                            }
                        }

                        Log::info('Import sync completed via Filament', [
                            'date_range' => $dateResolver->formatRange($from, $to),
                            'stats' => $stats,
                        ]);

                        $message = sprintf(
                            'Import completed successfully! Expenses: %d created, %d updated. Orders: %d created, %d updated.',
                            $stats['expenses']['created'],
                            $stats['expenses']['updated'],
                            $stats['orders']['created'],
                            $stats['orders']['updated'],
                        );

                        Notification::make()
                            ->title('Import Completed')
                            ->success()
                            ->body($message)
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Import sync failed via Filament', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Import Failed')
                            ->danger()
                            ->body('Import failed: '.$e->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
