<?php

namespace App\Console\Commands;

use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Removes accidental double-submitted payments from specific orders and reports
 * the reconciled state. A "duplicate" is a confirmed payment identical to an
 * earlier one on the same order — same method, amount, and recorder. The
 * earliest is kept; each later copy is reversed through OrderService so its
 * credit-term ledger charge is unwound and paid_amount is recomputed.
 *
 * Defaults to the six orders flagged during the double-submit investigation.
 * Always preview with --dry-run before applying against live.
 */
class DedupeOrderPayments extends Command
{
    protected $signature = 'orders:dedupe-payments
                            {ids?* : Order IDs to process (default: the six flagged orders)}
                            {--dry-run : Preview the removals; write nothing}';

    protected $description = 'Reverse duplicate (double-submitted) payments on the given orders and reconcile paid_amount / credit ledger.';

    /** Orders flagged during the double-submit investigation. */
    private const DEFAULT_IDS = [383, 414, 415, 416, 418, 422];

    public function handle(OrderService $orderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = $this->argument('ids');
        $ids = !empty($ids) ? array_map('intval', $ids) : self::DEFAULT_IDS;

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Processing orders: ' . implode(', ', $ids));

        $planned = [];   // duplicate payments to remove
        $missing = [];   // orders with no payment at all
        $notFound = [];

        foreach ($ids as $id) {
            $order = Order::find($id);
            if (!$order) {
                $notFound[] = $id;
                continue;
            }

            $payments = OrderPayment::where('order_id', $id)
                ->where('status', OrderPayment::STATUS_CONFIRMED)
                ->orderBy('id')
                ->get();

            if ($payments->isEmpty()) {
                $missing[] = [$id, $order->invoice_number ?: '-', number_format((float) $order->total_price, 2)];
                continue;
            }

            // Group identical payments; the first in each group is the keeper.
            $seen = [];
            foreach ($payments as $p) {
                $sig = implode('|', [
                    $p->payment_method,
                    number_format((float) $p->amount, 2, '.', ''),
                    $p->recorded_by ?? 'na',
                    $p->recorded_by_driver ?? 'na',
                ]);

                if (!isset($seen[$sig])) {
                    $seen[$sig] = $p->id; // keeper
                    continue;
                }

                $ledgerNet = (float) CustomerCreditLog::where('order_payment_id', $p->id)->sum('amount');
                $planned[] = [
                    'order' => $order,
                    'payment' => $p,
                    'row' => [
                        $id,
                        '#' . $p->id . ' (keep #' . $seen[$sig] . ')',
                        $p->payment_method,
                        number_format((float) $p->amount, 2),
                        number_format($ledgerNet, 2),
                        $p->recorderName(),
                    ],
                ];
            }
        }

        if ($notFound) {
            $this->warn('Not found: ' . implode(', ', $notFound));
        }
        if ($missing) {
            $this->warn(count($missing) . ' order(s) have NO payment (nothing to dedupe — record one manually):');
            $this->table(['Order', 'Invoice', 'Total'], $missing);
        }

        if (empty($planned)) {
            $this->info('No duplicate payments found. Nothing to remove.');
            $this->renderFinalState($ids);
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . count($planned) . ' duplicate payment(s) to reverse:');
        $this->table(
            ['Order', 'Remove Payment', 'Method', 'Amount', 'LedgerNet', 'Recorded By'],
            array_column($planned, 'row')
        );

        if ($dryRun) {
            $this->warn('Dry run only — nothing written. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Reverse the ' . count($planned) . ' duplicate payment(s) above?', true)) {
            $this->info('Aborted — no changes made.');
            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($planned as $item) {
            try {
                $orderService->deleteRecordedPayment($item['payment'], null);
                $removed++;
                $this->line('Reversed payment #' . $item['payment']->id . ' on order #' . $item['order']->id);
            } catch (\Throwable $e) {
                $this->error('Failed to reverse payment #' . $item['payment']->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Done — {$removed} duplicate payment(s) reversed.");
        $this->renderFinalState($ids);

        return self::SUCCESS;
    }

    /** Print the reconciled per-order state after processing. */
    private function renderFinalState(array $ids): void
    {
        $rows = [];
        foreach ($ids as $id) {
            $order = Order::find($id);
            if (!$order) {
                continue;
            }
            $order = $order->fresh();
            $count = OrderPayment::where('order_id', $id)
                ->where('status', OrderPayment::STATUS_CONFIRMED)
                ->count();
            $rows[] = [
                $id,
                $order->invoice_number ?: '-',
                number_format((float) $order->total_price, 2),
                number_format((float) $order->paid_amount, 2),
                $count,
                $order->payment_status,
            ];
        }

        $this->info('Final state:');
        $this->table(['Order', 'Invoice', 'Total', 'Paid', 'Payments', 'Status'], $rows);
    }
}
