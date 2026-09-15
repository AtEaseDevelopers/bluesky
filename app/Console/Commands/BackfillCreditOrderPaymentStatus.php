<?php

namespace App\Console\Commands;

use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use Illuminate\Console\Command;

class BackfillCreditOrderPaymentStatus extends Command
{
    protected $signature = 'orders:backfill-credit-payment-status
                            {--dry-run : Preview the orders that would change; write nothing}';

    protected $description = 'Re-derive payment_status for credit-term orders that still owe but were persisted as paid before the outstanding-balance fix.';

    public function handle(OrderService $orderService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $planned = [];

        Order::query()
            ->whereHas('payments', function ($q) {
                $q->where('status', OrderPayment::STATUS_CONFIRMED)
                    ->where('payment_method', 'credit-term');
            })
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$planned) {
                foreach ($orders as $order) {
                    // Fully settled orders already read correctly — leave them.
                    if ($order->creditOutstandingAmount() <= 0.009) {
                        continue;
                    }

                    $target = $this->deriveStatus($order);

                    if ($order->payment_status === $target) {
                        continue;
                    }

                    $planned[] = [
                        'order' => $order,
                        'from' => $order->payment_status,
                        'to' => $target,
                    ];
                }
            });

        if (empty($planned)) {
            $this->info('Nothing to backfill — no credit orders are mislabelled.');

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . count($planned) . ' order(s) to re-derive:');
        $this->table(
            ['Order', 'Outstanding (RM)', 'From', 'To'],
            collect($planned)->map(fn ($row) => [
                '#' . $row['order']->id,
                number_format($row['order']->creditOutstandingAmount(), 2),
                $row['from'],
                $row['to'],
            ])->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — no orders changed. Re-run without --dry-run to apply.');

            return 0;
        }

        $updated = 0;
        foreach ($planned as $row) {
            // Touch only the status column — paid_amount and generated documents
            // stay as they are; saveQuietly avoids firing model observers.
            $row['order']->forceFill(['payment_status' => $row['to']])->saveQuietly();
            $updated++;
        }

        $this->newLine();
        $this->info("Backfill complete — {$updated} order(s) updated.");

        return 0;
    }

    /**
     * Mirror OrderService::refreshPaymentStatus for a credit order that still
     * owes: partially settled reads as partial, past due reads as due, otherwise
     * unpaid.
     */
    private function deriveStatus(Order $order): string
    {
        if ($order->creditSettledAmount() > 0.009) {
            return Order::$payment_status['partial'];
        }

        if (
            $order->payment_due_date
            && $order->payment_due_date->toDateString() <= now()->toDateString()
        ) {
            return Order::$payment_status['payment_due'];
        }

        return Order::$payment_status['unpaid'];
    }
}
