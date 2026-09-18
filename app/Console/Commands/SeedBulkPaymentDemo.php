<?php

namespace App\Console\Commands;

use App\BulkPayment;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedBulkPaymentDemo extends Command
{
    protected $signature = 'demo:bulk-payment
                            {--email=creditdemo@example.com : Login email for the demo credit customer}
                            {--password=password : Login password for the demo credit customer}
                            {--orders=3 : Number of outstanding credit-term orders to create}
                            {--fresh : Delete any existing demo customer (and their orders) first}';

    protected $description = 'Create a credit customer with delivered credit-term orders that carry outstanding credit, to manually test the bulk payment page.';

    public function handle(OrderService $orderService): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $count = max(1, (int) $this->option('orders'));

        $existing = User::where('email', $email)->first();
        if ($existing && $this->option('fresh')) {
            $orderIds = Order::where('user_id', $existing->id)->pluck('id');
            OrderPayment::whereIn('order_id', $orderIds)->delete();
            CustomerCreditLog::where('user_id', $existing->id)->delete();
            BulkPayment::where('user_id', $existing->id)->delete();
            Order::whereIn('id', $orderIds)->delete();
            $existing->delete();
            $existing = null;
            $this->warn("Removed existing demo customer {$email}.");
        }

        $customer = $existing ?: User::forceCreate([
            'name' => 'Bulk Payment Demo',
            'email' => $email,
            'password' => Hash::make($password),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => 0,
            'status' => 'active',
            'registration_completed_at' => now(),
            'payment_method' => json_encode(['credit-term']),
            'login_code' => 'demo' . rand(100000, 999999),
            'billing_address' => '1 Market Street',
            'billing_postcode' => '50000',
            'billing_state' => 'WP Kuala Lumpur',
            'shipping_address' => '1 Market Street',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP Kuala Lumpur',
        ]);

        if ($existing) {
            $this->warn("Reusing existing customer {$email} (use --fresh to reset).");
        }

        $amounts = [];
        for ($i = 0; $i < $count; $i++) {
            $total = 25.00 + ($i * 15);
            $amounts[] = $total;

            $order = Order::forceCreate([
                'user_id' => $customer->id,
                'order_type' => 'registered',
                'total_price' => $total,
                'subtotal' => $total,
                'delivery_fee' => 0,
                'amount_adjustment' => 0,
                'order_weight' => 0,
                'paid_amount' => 0,
                'status' => 'delivered',
                'fulfillment_type' => 'delivery',
                'payment_method' => 'credit-term',
                'payment_status' => 'unpaid',
                'billing_address' => '1 Market Street',
                'billing_postcode' => '50000',
                'billing_state' => 'WP Kuala Lumpur',
            ]);

            // Charge the order to the credit account — this is the real flow that
            // leaves balanceDue()==0 while creditOutstandingAmount() stays owed.
            $orderService->recordPayment($order->fresh(), 'credit-term', (float) $total, null, null, null);

            $fresh = $order->fresh();
            $this->line(sprintf(
                '  Order #%d  total RM %.2f  balanceDue RM %.2f  creditOutstanding RM %.2f',
                $fresh->id,
                (float) $fresh->total_price,
                $fresh->balanceDue(),
                $fresh->creditOutstandingAmount()
            ));
        }

        $this->info('');
        $this->info('Demo credit customer ready.');
        $this->table(['Field', 'Value'], [
            ['Login URL', url('/login')],
            ['Bulk payment URL', url('/bulk-payments')],
            ['Email', $email],
            ['Password', $password],
            ['Outstanding orders', (string) $count],
            ['Total owed (RM)', number_format(array_sum($amounts), 2)],
            ['Credit balance', number_format((float) $customer->fresh()->credit_balance, 2)],
        ]);

        return self::SUCCESS;
    }
}
