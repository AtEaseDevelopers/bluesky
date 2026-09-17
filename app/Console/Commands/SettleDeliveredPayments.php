<?php

namespace App\Console\Commands;

use App\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class SettleDeliveredPayments extends Command
{
    protected $signature = 'orders:settle-delivered
                            {--dry-run : Preview the payments that would be recorded/held; write nothing}';

    protected $description = 'For driver-delivered orders: record a credit-term payment for credit customers, and put walk-in order payments on hold.';

    // What the command intends to do with a single order.
    private const ACTION_CREDIT_TERM = 'credit-term';

    private const ACTION_HOLD = 'hold';

    public function handle(OrderService $orderService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Only delivered orders that went out with a driver are in scope — both the
        // credit-term auto-charge and the walk-in hold mirror the driver delivery flow.
        $orders = Order::query()
            ->where('status', Order::$status['delivered'])
            ->whereNotNull('driver_id')
            ->with('customer')
            ->orderBy('id')
            ->get();

        $planned = [];
        $skipped = [];

        foreach ($orders as $order) {
            $balanceDue = $order->balanceDue();

            if ($order->isCreditCustomer()) {
                // Credit customer: record the outstanding balance as a credit-term
                // charge, unless the order is settled or already carries one.
                if ($balanceDue <= 0.009) {
                    $skipped[] = [$order->id, 'credit', 'no balance due'];
                    continue;
                }

                if ($order->hasConfirmedCreditTermPayment()) {
                    $skipped[] = [$order->id, 'credit', 'already has credit-term payment'];
                    continue;
                }

                $planned[] = [
                    'order_id' => $order->id,
                    'action' => self::ACTION_CREDIT_TERM,
                    'label' => 'credit',
                    'amount' => round($balanceDue, 2),
                ];
                continue;
            }

            if ($order->isWalkInOrder()) {
                // Walk-in: goods delivered but payment deferred — flag it on hold.
                if ($balanceDue <= 0.009) {
                    $skipped[] = [$order->id, 'walk-in', 'no balance due'];
                    continue;
                }

                if ($order->payment_held_at !== null) {
                    $skipped[] = [$order->id, 'walk-in', 'already on hold'];
                    continue;
                }

                $planned[] = [
                    'order_id' => $order->id,
                    'action' => self::ACTION_HOLD,
                    'label' => 'walk-in',
                    'amount' => round($balanceDue, 2),
                ];
                continue;
            }

            $skipped[] = [$order->id, $order->customerType(), 'not credit or walk-in'];
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . count($planned) . ' delivered order(s) to process:');
        $this->table(
            ['Order', 'Customer', 'Action', 'Amount (RM)'],
            collect($planned)->map(fn ($row) => [
                '#' . $row['order_id'],
                $row['label'],
                $row['action'] === self::ACTION_CREDIT_TERM ? 'Record credit-term' : 'Put on hold',
                number_format($row['amount'], 2),
            ])->all()
        );

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn(count($skipped) . ' order(s) skipped:');
            $this->table(['Order', 'Customer', 'Reason'], $skipped);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — no payments recorded or held. Re-run without --dry-run to apply.');

            return 0;
        }

        $creditTerm = 0;
        $held = 0;

        foreach ($planned as $row) {
            $order = $orders->firstWhere('id', $row['order_id']);

            if ($row['action'] === self::ACTION_CREDIT_TERM) {
                $orderService->recordPayment(
                    $order->fresh(),
                    'credit-term',
                    $row['amount'],
                    null,
                    null,
                    null,
                    $order->driver_id
                );
                $creditTerm++;
                continue;
            }

            // Hold: persist the deferred-payment intent, then let the service derive
            // the on_hold payment_status from it.
            $order->update([
                'payment_held_at' => now(),
                'payment_held_by' => $order->driver_id,
            ]);
            $orderService->refreshPaymentStatus($order->fresh());
            $held++;
        }

        $this->newLine();
        $this->info("Complete — {$creditTerm} credit-term payment(s) recorded, {$held} order(s) put on hold.");

        return 0;
    }
}
