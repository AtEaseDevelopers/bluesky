<?php

namespace App\Console\Commands;

use App\Services\OmsProductionResetService;
use Illuminate\Console\Command;

class PurgeProductionData extends Command
{
    protected $signature = 'oms:purge-production-data
                            {--dry-run : Show row counts only; do not delete}
                            {--customers : Also delete all customers and carts}
                            {--force : Required to run destructive purge}';

    protected $description = 'Delete all orders, invoices, payments, and optionally all customers (production reset).';

    public function handle(OmsProductionResetService $resetService): int
    {
        $counts = $resetService->counts();

        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());

        if ($this->option('dry-run')) {
            $this->warn('Dry run only — no data deleted.');
            $this->line('Re-run with --force to purge orders' . ($this->option('customers') ? ' and customers' : '') . '.');

            return 0;
        }

        if (!$this->option('force')) {
            $this->error('This permanently deletes production data. Re-run with --force to confirm.');

            return 1;
        }

        if (!$this->confirm('Delete ALL orders and related records' . ($this->option('customers') ? ', then ALL customers' : '') . '?')) {
            $this->warn('Aborted.');

            return 1;
        }

        $deleted = $resetService->purgeOrdersAndRelated();
        $this->info('Orders purge complete:');
        foreach ($deleted as $table => $count) {
            $this->line("  {$table}: {$count}");
        }

        if ($this->option('customers')) {
            $deletedCustomers = $resetService->purgeCustomersAndCarts();
            $this->info('Customer purge complete:');
            foreach ($deletedCustomers as $table => $count) {
                $this->line("  {$table}: {$count}");
            }
        }

        $this->newLine();
        $this->line('Next: import customers with');
        $this->line('  php artisan customers:import-bluesky-debtors storage/app/bluesky-debtors.xlsx');

        return 0;
    }
}
