<?php

namespace App\Console\Commands;

use App\Admin;
use App\BulkPayment;
use App\BulkPaymentOrder;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds an isolated dummy scenario to manually verify that confirming a bulk
 * payment keeps the approved payment record on the order. Re-runnable: it wipes
 * its own previous dummy data (matched by the marker email) before reseeding.
 */
class SeedDummyBulkPayment extends Command
{
    protected $signature = 'dummy:bulk-payment {--amount=99.00 : Credit-term charge / bulk payment amount}';

    protected $description = 'Create a dummy credit customer + order + pending bulk payment for manual testing';

    private const MARKER_EMAIL = 'dummy-bulk-test@example.com';

    public function handle(): int
    {
        $amount = round((float) $this->option('amount'), 2);

        $admin = Admin::first();
        if (!$admin) {
            $this->error('No admin exists to record the charge. Create an admin first.');
            return self::FAILURE;
        }

        $this->cleanupPrevious();

        $customer = User::forceCreate([
            'name' => 'Dummy Bulk Tester',
            'email' => self::MARKER_EMAIL,
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => 'credit-term',
            'login_code' => 'DUMMYBULK',
            'billing_address' => '1 Test St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Test St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);

        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => $amount,
            'subtotal' => $amount,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'packing',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Test St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        // Buy-now-pay-later: put the charge on the customer's credit account so
        // the order carries an outstanding credit-term balance (order balance 0,
        // ledger owes the amount).
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', $amount, null, null, $admin->id);

        // Customer submits a bulk payment (e-wallet) to settle it — pending until
        // an admin confirms it on the order summary.
        $bulk = BulkPayment::create([
            'user_id' => $customer->id,
            'total_amount' => $amount,
            'payment_method' => 'e-wallet',
            'payment_proof' => $this->makeProof('bulk-dummy-' . $customer->id . '.txt', 'orders/dummy'),
            'status' => BulkPayment::STATUS_PENDING,
            'notes' => 'Dummy manual-test bulk payment',
        ]);

        BulkPaymentOrder::create([
            'bulk_payment_id' => $bulk->id,
            'order_id' => $order->id,
            'amount' => $amount,
        ]);

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'e-wallet',
            'amount' => $amount,
            'status' => OrderPayment::STATUS_PENDING,
            'payment_proof' => $this->makeProof('proof-dummy.txt', Order::$path . '/' . $order->id . '/payments'),
            'submitted_by_user_id' => $customer->id,
            'bulk_payment_id' => $bulk->id,
            'notes' => 'Bulk payment #' . $bulk->id,
        ]);

        $order->refresh();
        $appUrl = rtrim(config('app.url'), '/');

        $this->info('Dummy bulk-payment scenario created.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Customer', $customer->name . ' (id ' . $customer->id . ')'],
            ['Customer credit balance', 'RM ' . number_format((float) $customer->fresh()->credit_balance, 2) . '  (owes ' . number_format($amount, 2) . ')'],
            ['Order', ($order->invoice_number ?: '#' . $order->id) . '  (id ' . $order->id . ')'],
            ['Order paid_amount / balance', 'RM ' . number_format((float) $order->paid_amount, 2) . ' / RM ' . number_format($order->balanceDue(), 2)],
            ['Credit outstanding', 'RM ' . number_format($order->creditOutstandingAmount(), 2)],
            ['Pending payment', 'OrderPayment #' . $payment->id . ' (e-wallet, ' . $payment->status . ') — "' . $payment->notes . '"'],
            ['Admin to log in as', $admin->username],
        ]);

        $this->newLine();
        $this->line('<comment>Manual verification steps:</comment>');
        $this->line('  1. Log in to admin, open the order summary:');
        $this->line('     <info>' . $appUrl . '/admin/order/summary/' . $order->id . '</info>');
        $this->line('  2. In "Payment history" you should see the PENDING e-wallet proof (#' . $payment->id . ').');
        $this->line('  3. Click Confirm on that payment.');
        $this->line('  4. EXPECT (this is the fix): the row STAYS as a CONFIRMED e-wallet payment');
        $this->line('     (RM ' . number_format($amount, 2) . ') with its "View proof" link — it is NOT deleted.');
        $this->line('  5. Order paid_amount stays RM ' . number_format($amount, 2) . ' (no double count); status becomes Paid.');
        $this->line('  6. Customer credit ledger shows a "Balance Settled" row for the same amount:');
        $this->line('     <info>' . $appUrl . '/admin/customers/' . $customer->id . '/edit</info>');
        $this->newLine();
        $this->line('Re-run <info>php artisan dummy:bulk-payment</info> to reset, or <info>--amount=250</info> for a different value.');

        return self::SUCCESS;
    }

    private function cleanupPrevious(): void
    {
        $old = User::where('email', self::MARKER_EMAIL)->get();
        foreach ($old as $customer) {
            $orderIds = Order::where('user_id', $customer->id)->pluck('id');
            $bulkIds = BulkPayment::where('user_id', $customer->id)->pluck('id');
            OrderPayment::whereIn('order_id', $orderIds)->delete();
            BulkPaymentOrder::whereIn('bulk_payment_id', $bulkIds)->delete();
            BulkPayment::whereIn('id', $bulkIds)->delete();
            \App\CustomerCreditLog::where('user_id', $customer->id)->delete();
            Order::whereIn('id', $orderIds)->delete();
            $customer->delete();
        }
    }

    private function makeProof(string $filename, string $dir): string
    {
        Storage::disk('local')->put($dir . '/' . $filename, "Dummy payment proof for manual testing.\n");
        return $filename;
    }
}
