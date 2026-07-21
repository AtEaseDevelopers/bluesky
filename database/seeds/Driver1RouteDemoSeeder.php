<?php

use App\Driver;
use App\Order;
use App\Product;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds "In Route" delivery orders assigned to driver1 so the driver portal's
 * Route tab (status = in_route) has demo data to work with.
 *
 * Idempotent: identifies its own rows by the RT-DEMO do_no prefix and skips
 * when they already exist. Run with:
 *   php artisan db:seed --class=Driver1RouteDemoSeeder
 */
class Driver1RouteDemoSeeder extends Seeder
{
    private const DO_PREFIX = 'RT-DEMO-';

    public function run(): void
    {
        $driver = Driver::where('username', 'driver1')->first();

        if (! $driver) {
            $this->command->error('driver1 not found. Run DemoDataSeeder first.');

            return;
        }

        $existing = Order::where('driver_id', $driver->id)
            ->where('do_no', 'like', self::DO_PREFIX . '%')
            ->count();

        if ($existing > 0) {
            $this->command->line("driver1 already has {$existing} RT-DEMO in-route order(s); nothing to do.");

            return;
        }

        $customers = User::where('role_slug', 'customer')->orderBy('id')->take(3)->get();
        $products = Product::orderBy('id')->take(4)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command->error('Need at least one customer and one product. Run DemoDataSeeder first.');

            return;
        }

        $slotId = DB::table('delivery_slots')->value('id'); // nullable-safe

        $definitions = [
            ['customer' => $customers[0], 'items' => [
                ['product' => $products[0], 'weight' => 2.0, 'unit_price' => 88.00],
                ['product' => $products[min(1, $products->count() - 1)], 'weight' => 1.5, 'unit_price' => 110.00],
            ]],
            ['customer' => $customers[min(1, $customers->count() - 1)], 'items' => [
                ['product' => $products[min(2, $products->count() - 1)], 'weight' => 3.0, 'unit_price' => 64.00],
            ]],
            ['customer' => $customers[min(2, $customers->count() - 1)], 'items' => [
                ['product' => $products[min(3, $products->count() - 1)], 'weight' => 4.5, 'unit_price' => 42.00],
                ['product' => $products[0], 'weight' => 1.0, 'unit_price' => 88.00],
            ]],
        ];

        DB::transaction(function () use ($definitions, $driver, $slotId) {
            foreach ($definitions as $index => $def) {
                /** @var User $customer */
                $customer = $def['customer'];

                $subtotal = 0.0;
                foreach ($def['items'] as $item) {
                    $subtotal += round($item['weight'] * $item['unit_price'], 2);
                }

                $order = Order::create([
                    'user_id' => $customer->id,
                    'order_type' => Order::$order_types['registered'],
                    'total_price' => $subtotal,
                    'subtotal' => $subtotal,
                    'delivery_fee' => 0,
                    'attn_name' => $customer->attn_name,
                    'attn_contact' => $customer->attn_contact,
                    'billing_address' => $customer->billing_address,
                    'billing_postcode' => $customer->billing_postcode,
                    'billing_state' => $customer->billing_state,
                    'shipping_address' => $customer->shipping_address ?: $customer->billing_address,
                    'shipping_postcode' => $customer->shipping_postcode ?: $customer->billing_postcode,
                    'shipping_state' => $customer->shipping_state ?: $customer->billing_state,
                    'payment_method' => $customer->isCreditCustomer() ? 'term' : 'cod',
                    'payment_status' => Order::$payment_status['unpaid'],
                    'paid_amount' => 0,
                    'status' => Order::$status['in_route'],
                    'fulfillment_type' => Order::$fulfillment_types['delivery'],
                    'driver_id' => $driver->id,
                    'delivery_slot_id' => $slotId,
                    'delivery_date' => now()->addDay()->toDateString(),
                    'delivery_time_slot' => '09:00 - 12:00',
                    'do_no' => self::DO_PREFIX . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'do_date' => now()->toDateString(),
                ]);

                foreach ($def['items'] as $item) {
                    $product = $item['product'];
                    $lineTotal = round($item['weight'] * $item['unit_price'], 2);

                    DB::table('order_products')->insert([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => 0,
                        'weight' => $item['weight'],
                        'unit_price' => $item['unit_price'],
                        'price' => $lineTotal,
                        'product_weight' => $product->weight ?? 0,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->command->info('Seeded ' . count($definitions) . ' in-route delivery orders for driver1.');
    }
}
