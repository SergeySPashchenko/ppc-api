<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Data Import & Sync</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Import historical business data from external MySQL database into the current system.
            </p>
            <div class="space-y-2 text-sm text-gray-500 dark:text-gray-400">
                <p>• Safe to run multiple times (idempotent)</p>
                <p>• Automatically handles customer and address normalization</p>
                <p>• Supports date ranges: single date, date range, or last N days</p>
                <p>• All operations are logged to Laravel log</p>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Logging</h3>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                All import operations are logged to Laravel log file:
                <code class="bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded">storage/logs/laravel.log</code>
            </p>
            <p class="text-sm text-blue-800 dark:text-blue-200 mt-2">
                Log entries include: created records, updated records, skipped records (with reasons), and errors (with full trace).
            </p>
        </div>
    </div>
</x-filament-panels::page>
