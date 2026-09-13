<?php

namespace App\Console\Commands;

use App\CustomerCreditLog;
use App\Order;
use App\Services\CreditService;
use Illuminate\Console\Command;

class BackfillCancelledOrderCreditReversals extends Command
{
    protected $signature = 'credit:backfill-cancelled-reversals
                            {--dry-run : Preview the reversals that would be posted; write nothing}';

    protected $description = 'Reverse credit charges on orders that were cancelled before the credit-reversal fix, so customers no longer owe (or lose credit) for goods never delivered.';

    public function handle(CreditService $creditService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Orders already reversed — skip them (reverseForOrder is idempotent, but
        // skipping keeps the preview honest).
        $reversedOrderIds = CustomerCreditLog::where('type', 'credit_reversal')
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->all();

        // Net charge-side movement (credit spent + "pay later" charges) per order,
        // for orders not yet reversed.
        $charges = CustomerCreditLog::query()
            ->whereIn('type', ['applied_to_order', 'credit_term'])
            ->whereNotNull('order_id')
            ->when(!empty($reversedOrderIds), fn ($q) => $q->whereNotIn('order_id', $reversedOrderIds))
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount) as charge_total')
            ->get();

        if ($charges->isEmpty()) {
            $this->info('Nothing to backfill — no un-reversed credit charges found.');

            return 0;
        }

        $orders = Order::query()
            ->whereIn('id', $charges->pluck('order_id')->all())
            ->where('status', Order::$status['cancelled'])
            ->with('customer')
            ->get()
            ->keyBy('id');

        $planned = [];
        $skipped = [];
        // Simulated running balance per customer for the preview.
        $projectedBalance = [];

        foreach ($charges as $charge) {
            $order = $orders->get($charge->order_id);

            if (!$order) {
                // Charge exists but the order isn't cancelled — nothing to reverse.
                continue;
            }

            $customer = $order->customer;
            if (!$customer || !$customer->isCreditCustomer()) {
                $skipped[] = [$order->id, $customer->id ?? '—', 'not a credit customer'];
                continue;
            }

            $chargeTotal = round((float) $charge->charge_total, 2);
            $reversal = -$chargeTotal; // charges are negative; reversal restores the balance
            if (abs($reversal) < 0.009) {
                continue;
            }

            if (!array_key_exists($customer->id, $projectedBalance)) {
                $projectedBalance[$customer->id] = (float) $customer->credit_balance;
            }
            $projectedBalance[$customer->id] = round($projectedBalance[$customer->id] + $reversal, 2);

            $planned[] = [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'reversal' => $reversal,
                'projected_balance' => $projectedBalance[$customer->id],
            ];
        }

        if (empty($planned)) {
            $this->info('Nothing to backfill — no cancelled credit orders with outstanding charges.');

            if (!empty($skipped)) {
                $this->newLine();
                $this->warn(count($skipped) . ' order(s) skipped:');
                $this->table(['Order', 'Customer', 'Reason'], $skipped);
            }

            return 0;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . count($planned) . ' cancelled order(s) to reverse:');
        $this->table(
            ['Order', 'Customer', 'Reversal (RM)', 'Balance After (RM)'],
            collect($planned)->map(fn ($row) => [
                '#' . $row['order_id'],
                $row['customer_id'] . ' — ' . $row['customer'],
                ($row['reversal'] >= 0 ? '+' : '') . number_format($row['reversal'], 2),
                number_format($row['projected_balance'], 2),
            ])->all()
        );

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn(count($skipped) . ' order(s) skipped:');
            $this->table(['Order', 'Customer', 'Reason'], $skipped);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — no reversals posted. Re-run without --dry-run to apply.');

            return 0;
        }

        $reversed = 0;
        foreach ($planned as $row) {
            $creditService->reverseForOrder($orders->get($row['order_id']));
            $reversed++;
        }

        $this->newLine();
        $this->info("Backfill complete — {$reversed} order(s) reversed.");

        return 0;
    }
}
