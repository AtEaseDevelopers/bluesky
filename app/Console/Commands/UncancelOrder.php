<?php

namespace App\Console\Commands;

use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\Product;
use App\Services\OrderStatusService;
use App\StockMovement;
use App\User;
use Illuminate\Console\Command;

/**
 * Restore a cancelled order to an active status (default: delivered).
 *
 * `cancelled` is a terminal state in OrderStatusService, and cancelling ran two
 * side effects that this command must undo so the data stays consistent:
 *   - CreditService::reverseForOrder — voided the customer-credit/credit-term
 *     payments (→ REJECTED) and posted a `credit_reversal` ledger entry.
 *   - StockService::restoreForOrder — restored stock and deleted the
 *     `sales_deduction` movements.
 *
 * The whole restoration runs in one transaction: all-or-nothing.
 */
class UncancelOrder extends Command
{
    protected $signature = 'orders:uncancel
                            {order : The cancelled order ID to restore}
                            {--to=delivered : Target status to restore the order to}
                            {--admin-id= : Admin ID to stamp on stock movements}
                            {--dry-run : Preview every change; write nothing}';

    protected $description = 'Restore a cancelled order to an active status (default: delivered): re-confirm voided credit payments, re-apply the credit charge, and re-deduct stock. All-or-nothing.';

    /** Active statuses a cancelled order may be restored to (mirrors OrderStatusService). */
    private const RESTORE_TARGETS = ['pending', 'packing', 'in_route', 'delivered'];

    public function handle(OrderStatusService $statusService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orderId = (int) $this->argument('order');
        $targetStatus = (string) $this->option('to');
        $adminId = $this->option('admin-id') !== null ? (int) $this->option('admin-id') : null;
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        if (!in_array($targetStatus, self::RESTORE_TARGETS, true)) {
            $this->error("Invalid target status '{$targetStatus}'. Use one of: " . implode(', ', self::RESTORE_TARGETS) . '.');

            return self::FAILURE;
        }

        $order = Order::find($orderId);
        if (!$order) {
            $this->error("Order #{$orderId} not found.");

            return self::FAILURE;
        }

        if ($order->status !== Order::$status['cancelled']) {
            $this->error("Order #{$orderId} is '{$order->status}', not 'cancelled'. Nothing to restore.");

            return self::FAILURE;
        }

        $this->info("{$prefix}Restoring order #{$orderId}: cancelled -> {$targetStatus}");
        $this->newLine();

        // ---- Plan: credit restoration ----
        $customer = $order->user_id ? User::find($order->user_id) : null;
        $isCreditCustomer = $customer && $customer->isCreditCustomer();

        $reversalTotal = 0.0;
        $voidedPayments = collect();
        if ($isCreditCustomer) {
            // credit_reversal is positive (it restored the balance on cancel).
            $reversalTotal = round((float) CustomerCreditLog::where('order_id', $orderId)
                ->where('type', 'credit_reversal')
                ->sum('amount'), 2);

            $voidedPayments = OrderPayment::where('order_id', $orderId)
                ->where('status', OrderPayment::STATUS_REJECTED)
                ->whereIn('payment_method', ['customer-credit', 'credit-term'])
                ->where('notes', 'like', 'Voided — order #' . $orderId . ' cancelled.%')
                ->get();
        }

        $hasCreditWork = $isCreditCustomer && abs($reversalTotal) >= 0.009;
        if ($hasCreditWork) {
            $balanceBefore = (float) $customer->credit_balance;
            $balanceAfter = round($balanceBefore - $reversalTotal, 2);
            $this->line('Credit:');
            $this->line("  customer:            #{$customer->id} {$customer->name}");
            $this->line('  re-apply charge:     ' . number_format(-$reversalTotal, 2) . ' RM (delete credit_reversal)');
            $this->line('  balance:             ' . number_format($balanceBefore, 2) . ' -> ' . number_format($balanceAfter, 2) . ' RM');
            $this->line('  re-confirm payments: ' . $voidedPayments->count()
                . ($voidedPayments->isNotEmpty() ? ' (' . $voidedPayments->pluck('payment_method')->implode(', ') . ')' : ''));
        } else {
            $this->line('Credit: no reversal to undo'
                . ($order->user_id && !$isCreditCustomer ? ' (not a credit customer).' : '.'));
        }
        $this->newLine();

        // ---- Plan: stock re-deduction ----
        // Pending/packing never hold deducted stock in the normal flow.
        $shouldDeductStock = !in_array($targetStatus, ['pending', 'packing'], true);
        $alreadyDeducted = StockMovement::where('order_id', $orderId)
            ->where('movement_type', 'sales_deduction')
            ->exists();

        $this->line('Stock:');
        if (!$shouldDeductStock) {
            $this->line("  target '{$targetStatus}' does not deduct stock — skip.");
        } elseif ($alreadyDeducted) {
            $this->line('  already has sales_deduction movements — skip re-deduction.');
        } else {
            $rows = $this->plannedDeductions($orderId);
            if (empty($rows)) {
                $this->line('  no active order products to deduct.');
            } else {
                $this->line('  re-deduct ' . count($rows) . ' product line(s):');
                $this->table(['Product', 'Qty', 'Weight'], $rows);
            }
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry run only — nothing written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        // ---- Apply ---- The service owns the restoration mechanics (credit undo,
        // conditional stock re-deduction, status update, invoice regeneration) so
        // the CLI and the admin UI stay in lock-step.
        $restored = $statusService->restoreFromCancelled($order->fresh(), $targetStatus, $adminId);

        $this->info("Order #{$orderId} restored to '{$restored->status}'.");

        return self::SUCCESS;
    }

    /**
     * Preview the lines StockService::deductForOrder would process, mirroring its
     * active-status filter. Read-only.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function plannedDeductions(int $orderId): array
    {
        $rows = [];

        $orderProducts = OrderProduct::where('order_id', $orderId)
            ->where('status', OrderProduct::$status['active'])
            ->get();

        foreach ($orderProducts as $orderProduct) {
            $product = Product::find($orderProduct->product_id);
            if (!$product) {
                continue;
            }

            $qty = $product->inventoryTracksWeight() ? '—' : (string) ($orderProduct->quantity ?? 0);
            $rawWeight = $orderProduct->weight ?? $orderProduct->product_weight;
            $weight = ($rawWeight !== null && $rawWeight !== '') ? (string) $rawWeight : '—';

            $rows[] = ['#' . $product->id . ' ' . $product->name, $qty, $weight];
        }

        return $rows;
    }
}
