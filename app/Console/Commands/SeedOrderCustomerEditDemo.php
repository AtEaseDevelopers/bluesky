<?php

namespace App\Console\Commands;

use App\Order;
use App\OrderProduct;
use App\Product;
use App\Services\OrderService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedOrderCustomerEditDemo extends Command
{
    protected $signature = 'demo:order-customer-edit
                            {--fresh : Delete any existing demo data first}';

    protected $description = 'Seed registered + credit customers, registered / credit / walk-in orders so the admin Order Edit "change customer / cross type" flow can be manually tested.';

    /** Marker used to find/clean up everything this command creates. */
    private const TAG = '[edit-demo]';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $customerIds = User::where('name', 'like', '%' . self::TAG)->pluck('id');
            $orderIds = Order::where(function ($q) use ($customerIds) {
                $q->whereIn('user_id', $customerIds)
                    ->orWhere('walk_in_name', 'like', '%' . self::TAG);
            })->pluck('id');
            \App\OrderPayment::whereIn('order_id', $orderIds)->delete();
            \App\CustomerCreditLog::whereIn('order_id', $orderIds)->orWhereIn('user_id', $customerIds)->delete();
            OrderProduct::whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->delete();
            User::whereIn('id', $customerIds)->delete();
            $this->warn('Removed existing edit-demo data.');
        }

        if (Product::where('status', Product::$status['active'])->doesntExist()) {
            $this->error('No active products found — cannot attach order lines. Seed products first.');

            return self::FAILURE;
        }

        // Registered COD customers to reassign between.
        $acme = $this->makeCustomer('Acme Seafood ' . self::TAG, 'cod', '10 Acme Road');
        $beta = $this->makeCustomer('Beta Foods ' . self::TAG, 'cod', '22 Beta Avenue');
        // Two credit customers to test credit-term charge transfer between accounts.
        $creditOne = $this->makeCustomer('Credit One ' . self::TAG, 'credit', '33 Credit Lane');
        $creditTwo = $this->makeCustomer('Credit Two ' . self::TAG, 'credit', '44 Credit Blvd');

        // A plain registered COD order — reassign to another account, or cross to walk-in.
        $registeredOrder = $this->makeRegisteredOrder($acme);

        // A credit order carrying an outstanding credit-term charge. Reassigning
        // to Credit Two transfers the charge; crossing to walk-in voids it.
        $creditOrder = $this->makeRegisteredOrder($creditOne, 'term');
        app(OrderService::class)->recordPayment($creditOrder->fresh(), 'credit-term', (float) $creditOrder->total_price, null, null, null);

        // Past walk-in orders — feed the "Reuse past walk-in" search.
        $this->makeWalkInOrder('John Tan ' . self::TAG, '0121112222');
        $this->makeWalkInOrder('Mary Lee ' . self::TAG, '0123334444');
        $this->makeWalkInOrder('John Tan ' . self::TAG, '0121112222'); // duplicate → search de-dupes
        // A standalone walk-in order to assign to a registered account.
        $walkInOrder = $this->makeWalkInOrder('Cash Buyer ' . self::TAG, '0129998888');

        $this->info('');
        $this->info('Order customer-edit demo ready.');
        $this->table(['Field', 'Value'], [
            ['Admin login', url('/admin/login')],
            ['Registered order (COD)', url('/admin/order/edit/' . urlencode(encrypt($registeredOrder->id)))],
            ['  #' . $registeredOrder->id, 'belongs to ' . $acme->name],
            ['Credit order (owes RM ' . number_format((float) $creditOrder->total_price, 2) . ')', url('/admin/order/edit/' . urlencode(encrypt($creditOrder->id)))],
            ['  #' . $creditOrder->id, 'belongs to ' . $creditOne->name],
            ['Walk-in order', url('/admin/order/edit/' . urlencode(encrypt($walkInOrder->id)))],
            ['  #' . $walkInOrder->id, 'Cash Buyer'],
            ['Reassign targets', $beta->name . ' | ' . $creditTwo->name],
            ['Past walk-ins to reuse', 'John Tan | Mary Lee'],
        ]);

        $this->line('');
        $this->line('Try these on the credit order:');
        $this->line('  • Reassign to "Credit Two" → the RM charge transfers to their ledger.');
        $this->line('  • Tick "Walk-in customer" → the charge is voided, Credit One restored.');
        $this->line('Walk-in order: untick "Walk-in customer" to assign it to a registered account,');
        $this->line('or keep it walk-in and "Reuse past walk-in" (search "john" / "0121").');

        return self::SUCCESS;
    }

    private function makeCustomer(string $name, string $type, string $address): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => 'editdemo' . rand(100000, 999999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $type,
            'customer_type' => $type,
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => $type === 'credit' ? json_encode(['term']) : 'cod',
            'login_code' => 'edit' . rand(100000, 999999),
            'billing_address' => $address,
            'billing_postcode' => '50000',
            'billing_state' => 'WP Kuala Lumpur',
            'shipping_address' => $address,
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP Kuala Lumpur',
        ]);
    }

    private function makeRegisteredOrder(User $customer, string $paymentMethod = 'cod'): Order
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => Order::$order_types['registered'],
            'total_price' => 0,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'packing',
            'fulfillment_type' => 'delivery',
            'payment_method' => $paymentMethod,
            'payment_status' => 'unpaid',
            'billing_address' => $customer->billing_address,
            'billing_postcode' => '50000',
            'billing_state' => 'WP Kuala Lumpur',
            'shipping_address' => $customer->shipping_address,
        ]);

        $this->attachProducts($order, $customer);

        return $order;
    }

    private function makeWalkInOrder(string $name, string $phone): Order
    {
        $order = Order::forceCreate([
            'user_id' => null,
            'order_type' => Order::$order_types['walk_in'],
            'walk_in_name' => $name,
            'walk_in_phone' => $phone,
            'total_price' => 0,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'packing',
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'billing_address' => 'Counter',
        ]);

        $this->attachProducts($order, null);

        return $order;
    }

    /** Attach a couple of real active product lines and recompute the totals. */
    private function attachProducts(Order $order, ?User $customer): void
    {
        $products = Product::where('status', Product::$status['active'])
            ->inRandomOrder()
            ->take(2)
            ->get();

        $subtotal = 0;
        $orderWeight = 0;

        foreach ($products as $product) {
            if (in_array($product->sell_in, [Product::SELL_IN_WEIGHT, Product::SELL_IN_QTY_BILL_WEIGHT], true)) {
                $line = $product->resolveLineInputs(1, 1.0, true);
            } else {
                $line = $product->resolveLineInputs(2, null);
            }

            $unitPrice = Product::get_today_price($product->id, $customer);
            $price = $unitPrice * $line['bill_amount'];

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => Product::orderLineName($product),
                'quantity' => $line['quantity'],
                'weight' => $line['weight'],
                'product_weight' => $line['product_weight'],
                'unit_price' => $unitPrice,
                'price' => $price,
                'remark' => '',
                'status' => OrderProduct::$status['active'],
            ]);

            $subtotal += $price;
            $orderWeight += $line['order_weight'];
        }

        $order->fill([
            'subtotal' => $subtotal,
            'total_price' => $subtotal + (float) $order->delivery_fee,
            'order_weight' => $orderWeight,
        ])->save();
    }
}
