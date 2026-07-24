<?php

use App\Driver;
use App\Order;
use App\OrderPayment;
use App\Product;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds demo orders covering every order status, payment status, and common
 * customer scenarios (COD, credit, walk-in, general link, pickup / in-store).
 *
 * Idempotent — rows are keyed by do_no prefix STATUS-DEMO-.
 *
 *   php artisan db:seed --class=OrderStatusDemoSeeder
 */
class OrderStatusDemoSeeder extends Seeder
{
    private const DO_PREFIX = 'STATUS-DEMO-';

    public function run(): void
    {
        $driver1 = Driver::where('username', 'driver1')->first();
        $driver2 = Driver::where('username', 'driver2')->first();

        if (!$driver1) {
            $this->command->error('driver1 not found. Run DemoDataSeeder first.');

            return;
        }

        $customers = User::whereIn('email', [
            'ocean@demo.test',
            'harbour@demo.test',
            'blueocean@demo.test',
            'seaside@demo.test',
        ])->get()->keyBy('email');

        if ($customers->count() < 4) {
            $this->command->error('Demo customers not found. Run DemoDataSeeder first.');

            return;
        }

        $products = Product::orderBy('id')->take(4)->get();
        if ($products->isEmpty()) {
            $this->command->error('No products found. Run DemoDataSeeder first.');

            return;
        }

        $slotId = DB::table('delivery_slots')->value('id');
        $timeSlot = '09:00 - 12:00';
        $deliveryDate = now()->addDay()->toDateString();

        $definitions = [
            [
                'do_no' => self::DO_PREFIX . '01-PENDING',
                'label' => 'Pending COD delivery',
                'status' => Order::$status['pending'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['ocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '02-PACKING',
                'label' => 'Packing COD delivery',
                'status' => Order::$status['packing'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['harbour@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '03-INROUTE-COD',
                'label' => 'In route COD (collect on delivery)',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['ocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cash',
            ],
            [
                'do_no' => self::DO_PREFIX . '04-INROUTE-CREDIT',
                'label' => 'In route credit (partial paid)',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['partial'],
                'paid_amount' => 100.00,
                'customer' => $customers['blueocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'term',
                'payment_due_date' => now()->addDays(30)->toDateString(),
                'payments' => [
                    ['method' => 'cash', 'amount' => 100.00],
                ],
            ],
            [
                'do_no' => self::DO_PREFIX . '05-DELIVERED-COD',
                'label' => 'Delivered COD unpaid',
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['ocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'qr',
                'delivery_confirmed_at' => now()->subHours(2),
            ],
            [
                'do_no' => self::DO_PREFIX . '06-DELIVERED-CREDIT-DUE',
                'label' => 'Delivered credit payment due',
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['payment_due'],
                'customer' => $customers['blueocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'term',
                'payment_due_date' => now()->subDays(5)->toDateString(),
                'delivery_confirmed_at' => now()->subDay(),
            ],
            [
                'do_no' => self::DO_PREFIX . '07-DELIVERED-PAID',
                'label' => 'Delivered fully paid',
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['paid'],
                'paid_amount' => 0, // set from line total below
                'customer' => $customers['seaside@demo.test'],
                'driver' => $driver2 ?: $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'bank-transfer',
                'delivery_confirmed_at' => now()->subDays(2),
                'payments' => [
                    ['method' => 'bank-transfer', 'amount' => 0], // filled after total computed
                ],
            ],
            [
                'do_no' => self::DO_PREFIX . '08-COMPLETED',
                'label' => 'Completed order',
                'status' => Order::$status['completed'],
                'payment_status' => Order::$payment_status['paid'],
                'paid_amount' => 0,
                'customer' => $customers['seaside@demo.test'],
                'driver' => $driver2 ?: $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cash',
                'completed_at' => now()->subDays(3),
                'payments' => [
                    ['method' => 'cash', 'amount' => 0],
                ],
            ],
            [
                'do_no' => self::DO_PREFIX . '09-CANCELLED',
                'label' => 'Cancelled order',
                'status' => Order::$status['cancelled'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['harbour@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '10-WALKIN',
                'label' => 'Admin walk-in delivery (COD)',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => null,
                'order_type' => Order::$order_types['walk_in'],
                'walk_in_name' => 'Walk-in Guest Ah Meng',
                'walk_in_phone' => '0198765432',
                'attn_name' => 'Walk-in Guest Ah Meng',
                'attn_contact' => '0198765432',
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cash',
            ],
            [
                'do_no' => self::DO_PREFIX . '11-GENERAL',
                'label' => 'General link order (COD)',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => null,
                'is_general' => true,
                'order_type' => Order::$order_types['public'],
                'attn_name' => 'Public Link Buyer',
                'attn_contact' => '0161122334',
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '12-PICKUP-PACKING',
                'label' => 'Customer pickup — packing',
                'status' => Order::$status['packing'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['ocean@demo.test'],
                'driver' => null,
                'fulfillment_type' => Order::$fulfillment_types['pickup'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '13-INSTORE',
                'label' => 'In-store walk-in — delivered',
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['paid'],
                'paid_amount' => 0,
                'customer' => null,
                'order_type' => Order::$order_types['walk_in'],
                'walk_in_name' => 'Counter Walk-in',
                'walk_in_phone' => '0112233445',
                'attn_name' => 'Counter Walk-in',
                'attn_contact' => '0112233445',
                'driver' => null,
                'fulfillment_type' => Order::$fulfillment_types['pickup'],
                'payment_method' => 'in-store',
                'payments' => [
                    ['method' => 'in-store', 'amount' => 0],
                ],
            ],
            [
                'do_no' => self::DO_PREFIX . '13-COURIER',
                'label' => 'Courier — in route awaiting handover',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['unpaid'],
                'customer' => $customers['ocean@demo.test'],
                'driver' => null,
                'fulfillment_type' => Order::$fulfillment_types['courier'],
                'payment_method' => 'cod',
            ],
            [
                'do_no' => self::DO_PREFIX . '14-PAYMENT-PENDING',
                'label' => 'In route — payment proof pending review',
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['pending'],
                'customer' => $customers['harbour@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'bank-transfer',
            ],
            [
                'do_no' => self::DO_PREFIX . '15-CREDIT-PAID',
                'label' => 'Delivered credit — fully paid',
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['paid'],
                'paid_amount' => 0,
                'customer' => $customers['blueocean@demo.test'],
                'driver' => $driver1,
                'fulfillment_type' => Order::$fulfillment_types['delivery'],
                'payment_method' => 'term',
                'payment_due_date' => now()->subDays(10)->toDateString(),
                'delivery_confirmed_at' => now()->subDays(8),
                'payments' => [
                    ['method' => 'bank-transfer', 'amount' => 0],
                ],
            ],
        ];

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($definitions, $products, $slotId, $timeSlot, $deliveryDate, &$created, &$updated) {
            foreach ($definitions as $index => $def) {
                $items = $this->lineItemsForIndex($products, $index);
                $subtotal = round(collect($items)->sum('price'), 2);

                if (($def['paid_amount'] ?? null) === 0 && in_array($def['payment_status'], [
                    Order::$payment_status['paid'],
                    Order::$payment_status['partial'],
                ], true)) {
                    $def['paid_amount'] = $def['payment_status'] === Order::$payment_status['partial']
                        ? ($def['paid_amount'] ?: 100.00)
                        : $subtotal;
                }

                if (!empty($def['payments'])) {
                    foreach ($def['payments'] as $i => $payment) {
                        if ($payment['amount'] === 0) {
                            $def['payments'][$i]['amount'] = $def['paid_amount'] ?? $subtotal;
                        }
                    }
                }

                /** @var User|null $customer */
                $customer = $def['customer'] ?? null;

                $attrs = [
                    'user_id' => $customer?->id,
                    'is_general' => $def['is_general'] ?? false,
                    'order_type' => $def['order_type'] ?? Order::$order_types['registered'],
                    'walk_in_name' => $def['walk_in_name'] ?? null,
                    'walk_in_phone' => $def['walk_in_phone'] ?? null,
                    'total_price' => $subtotal,
                    'subtotal' => $subtotal,
                    'delivery_fee' => 0,
                    'attn_name' => $def['attn_name'] ?? $customer?->attn_name,
                    'attn_contact' => $def['attn_contact'] ?? $customer?->attn_contact,
                    'billing_address' => $customer?->billing_address ?? 'Demo Billing Address',
                    'billing_postcode' => $customer?->billing_postcode ?? '50000',
                    'billing_state' => $customer?->billing_state ?? 'Kuala Lumpur',
                    'shipping_address' => $customer?->shipping_address ?? $customer?->billing_address ?? 'Demo Shipping Address',
                    'shipping_postcode' => $customer?->shipping_postcode ?? '50000',
                    'shipping_state' => $customer?->shipping_state ?? 'Kuala Lumpur',
                    'payment_method' => $def['payment_method'],
                    'payment_status' => $def['payment_status'],
                    'paid_amount' => $def['paid_amount'] ?? 0,
                    'payment_due_date' => $def['payment_due_date'] ?? null,
                    'status' => $def['status'],
                    'fulfillment_type' => $def['fulfillment_type'],
                    'driver_id' => $def['driver']?->id,
                    'delivery_slot_id' => ($def['fulfillment_type'] ?? Order::$fulfillment_types['delivery']) === Order::$fulfillment_types['delivery']
                        ? $slotId
                        : null,
                    'delivery_date' => $deliveryDate,
                    'delivery_time_slot' => $timeSlot,
                    'do_date' => now()->toDateString(),
                    'invoice_number' => 'INV-' . str_replace(self::DO_PREFIX, 'STATUS-', $def['do_no']),
                    'delivery_confirmed_at' => $def['delivery_confirmed_at'] ?? null,
                    'completed_at' => $def['completed_at'] ?? null,
                ];

                $existing = Order::where('do_no', $def['do_no'])->first();
                $order = Order::updateOrCreate(['do_no' => $def['do_no']], $attrs);

                $existing ? $updated++ : $created++;

                DB::table('order_products')->where('order_id', $order->id)->delete();
                foreach ($items as $item) {
                    DB::table('order_products')->insert([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'weight' => $item['weight'],
                        'unit_price' => $item['unit_price'],
                        'price' => $item['price'],
                        'product_weight' => $item['product_weight'],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                OrderPayment::where('order_id', $order->id)->delete();
                foreach ($def['payments'] ?? [] as $payment) {
                    OrderPayment::create([
                        'order_id' => $order->id,
                        'payment_method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'status' => OrderPayment::STATUS_CONFIRMED,
                        'recorded_by_driver' => $def['driver']?->id,
                    ]);
                }
            }
        });

        $this->command->info("Order status demo data seeded ({$created} created, {$updated} updated).");
        $this->command->line('Driver portal — driver1 / password — filter Processing / In Route / Delivered');
        $this->command->line('Admin — Manage Orders — search STATUS-DEMO');
    }

    /** @return list<array<string, mixed>> */
    private function lineItemsForIndex($products, int $index): array
    {
        $a = $products[0];
        $b = $products[min(1, $products->count() - 1)];
        $c = $products[min(2, $products->count() - 1)];

        $sets = [
            [['product' => $a, 'weight' => 2.0, 'unit_price' => 85.00]],
            [['product' => $b, 'weight' => 1.5, 'unit_price' => 120.00]],
            [['product' => $a, 'weight' => 1.0, 'unit_price' => 85.00], ['product' => $c, 'weight' => 2.0, 'unit_price' => 45.00]],
            [['product' => $c, 'weight' => 3.0, 'unit_price' => 45.00], ['product' => $b, 'weight' => 1.0, 'unit_price' => 120.00]],
            [['product' => $a, 'weight' => 2.5, 'unit_price' => 88.00]],
            [['product' => $b, 'weight' => 2.0, 'unit_price' => 118.00]],
            [['product' => $c, 'weight' => 4.0, 'unit_price' => 46.00]],
            [['product' => $a, 'weight' => 1.2, 'unit_price' => 90.00], ['product' => $b, 'weight' => 0.8, 'unit_price' => 125.00]],
            [['product' => $b, 'weight' => 1.0, 'unit_price' => 120.00]],
            [['product' => $a, 'weight' => 1.8, 'unit_price' => 86.00]],
            [['product' => $c, 'weight' => 2.2, 'unit_price' => 44.00]],
            [['product' => $a, 'weight' => 3.0, 'unit_price' => 85.00]],
            [['product' => $b, 'weight' => 0.5, 'unit_price' => 120.00]],
            [['product' => $c, 'weight' => 1.5, 'unit_price' => 45.00]],
            [['product' => $a, 'weight' => 2.0, 'unit_price' => 87.00], ['product' => $c, 'weight' => 1.0, 'unit_price' => 46.00]],
        ];

        $set = $sets[$index % count($sets)];

        return collect($set)->map(function ($row) {
            $product = $row['product'];
            $lineTotal = round($row['weight'] * $row['unit_price'], 2);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 0,
                'weight' => $row['weight'],
                'unit_price' => $row['unit_price'],
                'price' => $lineTotal,
                'product_weight' => $product->weight ?? 1,
            ];
        })->all();
    }
}
