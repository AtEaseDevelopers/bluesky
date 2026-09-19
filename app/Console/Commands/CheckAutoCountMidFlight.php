<?php

namespace App\Console\Commands;

use App\Order;
use Illuminate\Console\Command;

/**
 * Pre-cutover safety check for the "drop SO+DO, sync straight to Invoice/Cash
 * Sale" switch.
 *
 * The retired pipeline created a Sales Order + Delivery Order first and wrote the
 * DO number back (api_do_id) before the Invoice/Cash Sale stage. The new flow
 * builds the Invoice/Cash Sale directly from the order lines and IGNORES any
 * existing DO. So an order left mid-flight — a real DO already in AutoCount
 * (api_do_id set) but no invoice yet (api_invoice_id NULL) — would have its stock
 * deducted twice (once by the approved DO, once by the new Invoice/Cash Sale) and
 * leave an orphaned DO behind.
 *
 * Run this before deploying the new plugin. A clean result (exit 0) means it is
 * safe to cut over; anything listed must be drained/reconciled first.
 */
class CheckAutoCountMidFlight extends Command
{
    protected $signature = 'orders:check-autocount-midflight';

    protected $description = 'List orders left mid-flight in the retired SO+DO pipeline (DO created, no invoice yet) that would double-deduct stock after the direct Invoice/Cash Sale cutover.';

    public function handle(): int
    {
        $orders = Order::query()
            ->whereNotNull('api_do_id')
            ->whereNull('api_invoice_id')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Clean — no orders are mid-flight in the SO+DO pipeline. Safe to cut over.');

            return 0;
        }

        $this->warn($orders->count() . ' order(s) have a Delivery Order in AutoCount but no invoice yet.');
        $this->line('Cutting over now would double-deduct their stock and orphan the DO. Reconcile each first:');
        $this->line(' • cancel/keep the existing DO in AutoCount, then');
        $this->line(' • re-queue with: php artisan orders:reset-autocount-sync --force --id=<id> (clears api_do_id).');
        $this->newLine();

        $this->table(
            ['Order', 'Invoice', 'Sync Status', 'DO', 'INV'],
            $orders->map(fn (Order $o) => [
                '#' . $o->id,
                $o->invoice_number ?: '—',
                $o->autocount_sync_status ?: '—',
                $o->api_do_id ?: '—',
                $o->api_invoice_id ?: '—',
            ])->all()
        );

        // Non-zero exit so a deploy script can gate the cutover on a clean result.
        return 1;
    }
}
