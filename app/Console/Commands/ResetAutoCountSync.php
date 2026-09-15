<?php

namespace App\Console\Commands;

use App\Order;
use Illuminate\Console\Command;

class ResetAutoCountSync extends Command
{
    protected $signature = 'orders:reset-autocount-sync
                            {--dry-run : Preview the orders that would change; write nothing}
                            {--force : Also re-queue orders already sent to AutoCount and clear their DO/invoice refs (may create duplicate documents)}
                            {--unqueue : Reverse: set queued (pending_sync) and errored (sync_error) orders back to pending so the plugin ignores them}
                            {--id=* : Restrict to specific order id(s)}';

    protected $description = 'Re-queue paid & completed orders for AutoCount sync (autocount_sync_status = pending_sync), or --unqueue to reverse.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $unqueue = (bool) $this->option('unqueue');
        $ids = array_filter((array) $this->option('id'), fn ($id) => $id !== null && $id !== '');

        if ($unqueue) {
            return $this->unqueue($ids, $dryRun);
        }

        // Only paid + completed orders are eligible to sync (mirrors
        // AutoCountApiService::baseOrderQuery).
        $query = Order::query()
            ->where('payment_status', Order::$payment_status['paid'])
            ->where('status', Order::$status['completed']);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        // Without --force, never touch orders that already have a document in
        // AutoCount — re-queuing those would create duplicates.
        if (!$force) {
            $query->whereNull('api_do_id')->whereNull('api_invoice_id');
        }

        $orders = $query->orderBy('id')->get();

        if ($orders->isEmpty()) {
            $this->info('No eligible orders to re-queue.');

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $orders->count() . ' order(s) will be set to pending_sync'
            . ($force ? ' (DO/invoice refs cleared)' : '') . ':');

        $this->table(
            ['Order', 'Invoice', 'From', 'DO', 'INV'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                $o->invoice_number ?: '—',
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
        $this->info("Done — {$updated} order(s) re-queued for AutoCount sync.");

        return 0;
    }

    /**
     * Reverse queuing: set orders currently waiting for the plugin
     * (pending_sync) or stuck on an error (sync_error) back to the unqueued
     * 'pending' state. Orders with a document in progress (do_created / synced /
     * paid_synced) are left alone.
     *
     * @param  array<int|string>  $ids
     */
    protected function unqueue(array $ids, bool $dryRun): int
    {
        $query = Order::query()->whereIn('autocount_sync_status', ['pending_sync', 'sync_error']);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $orders = $query->orderBy('id')->get();

        if ($orders->isEmpty()) {
            $this->info('No pending_sync / sync_error orders to un-queue.');

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            . $orders->count() . ' order(s) will be set back to pending (un-queued):');

        $this->table(
            ['Order', 'Invoice', 'From', 'DO', 'INV'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                $o->invoice_number ?: '—',
                $o->autocount_sync_status ?: '—',
                $o->api_do_id ?: '—',
                $o->api_invoice_id ?: '—',
            ])->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — nothing changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $updated = $query->update([
            'autocount_sync_status' => 'pending',
            'autocount_synced_at' => null,
        ]);

        $this->newLine();
        $this->info("Done — {$updated} order(s) un-queued (set to pending).");

        return 0;
    }
}
