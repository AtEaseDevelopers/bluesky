<?php

namespace App\Console\Commands;

use App\Order;
use Illuminate\Console\Command;

class QueueCreditSync extends Command
{
    protected $signature = 'orders:queue-credit-sync
                            {--dry-run : Preview the orders that would change; write nothing}
                            {--force : Also re-queue credit orders that already have a DO/invoice in AutoCount, clearing their refs (may create duplicate documents)}
                            {--id=* : Restrict to specific order id(s)}';

    protected $description = 'Set fulfilled (delivered/completed) credit-customer orders to autocount_sync_status = pending_sync so the AutoCount plugin picks them up.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $ids = array_filter((array) $this->option('id'), fn ($id) => $id !== null && $id !== '');

        // Credit orders sync once the goods are delivered (or completed) — the
        // balance is carried on the credit account and settled later. Mirrors
        // Order::canSyncToAutoCount() for credit customers.
        $query = Order::query()
            ->forCreditCustomers()
            ->whereIn('status', [
                Order::$status['delivered'],
                Order::$status['completed'],
            ]);

        if (!empty($ids)) {
            $query->whereIn('orders.id', $ids);
        }

        // Without --force, leave orders already sent to AutoCount alone:
        // re-queuing a synced / paid_synced order (or one that already carries a
        // DO/invoice ref) would create duplicate documents.
        if (!$force) {
            $query->whereNotIn('autocount_sync_status', ['synced', 'paid_synced'])
                ->whereNull('api_do_id')
                ->whereNull('api_invoice_id');
        }

        $orders = $query->orderBy('user_id')->orderBy('orders.id')->get();

        if ($orders->isEmpty()) {
            $this->info('No eligible credit orders to queue.');

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $orders->count() . ' credit order(s) across ' . $orders->pluck('user_id')->unique()->count()
            . ' customer(s) will be set to pending_sync'
            . ($force ? ' (DO/invoice refs cleared)' : '') . ':');

        $this->table(
            ['Order', 'Customer', 'Invoice', 'Status', 'From', 'DO', 'INV'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                optional($o->customer)->name ?? ('user ' . $o->user_id),
                $o->invoice_number ?: '—',
                $o->status,
                $o->autocount_sync_status ?: '—',
                $o->api_do_id ?: '—',
                $o->api_invoice_id ?: '—',
            ])->all()
        );

        if ($force) {
            $this->warn('--force clears api_do_id / api_invoice_id: the plugin will create NEW documents. '
                . 'Only proceed if these orders have no (or deleted) documents in AutoCount, or you will get duplicates.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — nothing changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $payload = [
            'autocount_sync_status' => 'pending_sync',
            'autocount_synced_at' => null,
        ];
        if ($force) {
            $payload['api_do_id'] = null;
            $payload['api_invoice_id'] = null;
        }

        $updated = $query->update($payload);

        $this->newLine();
        $this->info("Done — {$updated} credit order(s) set to pending_sync.");

        return 0;
    }
}
