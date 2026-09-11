<?php

namespace App\Console\Commands;

use App\CustomerCreditLog;
use App\OrderPayment;
use App\Services\CreditService;
use Illuminate\Console\Command;

class BackfillCreditTermLedger extends Command
{
    protected $signature = 'credit:backfill-credit-term
                            {--dry-run : Preview the ledger entries that would be created; write nothing}';

    protected $description = 'Backfill customer credit ledger entries for confirmed credit-term payments recorded before the outstanding-balance fix.';

    public function handle(CreditService $creditService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Confirmed credit-term payments that settled an order balance but have
        // no matching ledger movement yet (order_payment_id not seen).
        $backfilledPaymentIds = CustomerCreditLog::where('type', 'credit_term')
            ->whereNotNull('order_payment_id')
            ->pluck('order_payment_id')
            ->all();

        $payments = OrderPayment::query()
            ->where('payment_method', 'credit-term')
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->where('amount', '>', 0)
            ->when(!empty($backfilledPaymentIds), fn ($q) => $q->whereNotIn('id', $backfilledPaymentIds))
            ->with('order.customer')
            ->orderBy('id') // chronological so running balances stay correct
            ->get();

        if ($payments->isEmpty()) {
            $this->info('Nothing to backfill — all credit-term payments already have ledger entries.');

            return 0;
        }

        $planned = [];
        $skipped = [];
        // Simulated running balance per customer for the dry-run preview.
        $projectedBalance = [];

        foreach ($payments as $payment) {
            $order = $payment->order;
            $customer = $order?->customer;

            if (!$order || !$customer) {
                $skipped[] = [$payment->id, $order->id ?? '—', '—', 'missing order/customer'];
                continue;
            }

            if (!$customer->isCreditCustomer()) {
                $skipped[] = [$payment->id, $order->id, $customer->id, 'not a credit customer'];
                continue;
            }

            $amount = (float) $payment->amount;

            if (!array_key_exists($customer->id, $projectedBalance)) {
                $projectedBalance[$customer->id] = (float) $customer->credit_balance;
            }
            $projectedBalance[$customer->id] = round($projectedBalance[$customer->id] - $amount, 2);

            $planned[] = [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'customer' => $customer->name,
                'amount' => $amount,
                'projected_balance' => $projectedBalance[$customer->id],
            ];
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . count($planned) . ' credit-term payment(s) to backfill:');
        $this->table(
            ['Payment', 'Order', 'Customer', 'Charge (RM)', 'Balance After (RM)'],
            collect($planned)->map(fn ($row) => [
                $row['payment_id'],
                '#' . $row['order_id'],
                $row['customer_id'] . ' — ' . $row['customer'],
                '-' . number_format($row['amount'], 2),
                number_format($row['projected_balance'], 2),
            ])->all()
        );

        if (!empty($skipped)) {
            $this->newLine();
            $this->warn(count($skipped) . ' payment(s) skipped:');
            $this->table(['Payment', 'Order', 'Customer', 'Reason'], $skipped);
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run only — no ledger entries created. Re-run without --dry-run to apply.');

            return 0;
        }

        $created = 0;
        foreach ($planned as $row) {
            $payment = $payments->firstWhere('id', $row['payment_id']);
            $customer = $payment->order->customer;

            $creditService->recordCreditTermCharge(
                $customer,
                $row['amount'],
                $payment->order,
                $payment->id,
                $payment->recorded_by,
                $payment->recorded_by_driver,
                'Backfill: credit term charge on order #' . $row['order_id']
            );

            $created++;
        }

        $this->newLine();
        $this->info("Backfill complete — {$created} ledger entry(ies) created.");

        return 0;
    }
}
