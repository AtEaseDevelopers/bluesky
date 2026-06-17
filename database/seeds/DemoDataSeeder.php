<?php

use App\Admin;
use App\Driver;
use App\Order;
use App\Product;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedAdmins();
            $uomId = $this->seedUoms();
            $this->seedCustomerCategories();
            $this->seedAreas();
            $drivers = $this->seedDrivers();
            $customers = $this->seedCustomers($drivers);
            $products = $this->seedProducts($uomId);
            $this->seedProductVisibilities($customers, $products);
            $slots = $this->seedDeliverySlots();
            $this->seedOrders($customers, $drivers, $products, $slots);
        });

        $this->command->info('Demo data seeded successfully.');
        $this->command->line('');
        $this->command->line('Admin portal  — username: superadmin  password: password');
        $this->command->line('Driver portal — username: driver1     password: password  (also driver2, driver3)');
        $this->command->line('Member portal — email: ocean@demo.test  password: password  (also harbour@demo.test, blueocean@demo.test, seaside@demo.test)');
    }

    private function seedAdmins(): void
    {
        foreach ([
            ['username' => 'superadmin', 'name' => 'Super Admin', 'email' => 'superadmin@bluesky.test', 'role' => 'superadmin'],
            ['username' => 'manager', 'name' => 'Operations Manager', 'email' => 'manager@bluesky.test', 'role' => 'management'],
        ] as $row) {
            $admin = Admin::updateOrCreate(
                ['username' => $row['username']],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => Hash::make('password'),
                ]
            );

            DB::table('admins')->where('id', $admin->id)->update(['role' => $row['role']]);
        }
    }

    private function seedUoms(): int
    {
        foreach (['KG', 'PCS', 'BOX'] as $name) {
            DB::table('uoms')->updateOrInsert(
                ['uom_name' => $name],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        return (int) DB::table('uoms')->where('uom_name', 'KG')->value('id');
    }

    private function seedCustomerCategories(): void
    {
        foreach (['restaurant', 'hotel', 'retail', 'wholesale'] as $category) {
            DB::table('customer_categories')->updateOrInsert(
                ['category' => $category],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedAreas(): void
    {
        foreach (['Kuala Lumpur', 'Petaling Jaya', 'Shah Alam', 'Klang', 'Subang Jaya'] as $area) {
            DB::table('areas')->updateOrInsert(
                ['area_name' => $area],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedDrivers(): array
    {
        $rows = [
            ['lorry_number' => 'LRY-001', 'name' => 'Ahmad Rizal', 'phone' => '0123001001', 'username' => 'driver1'],
            ['lorry_number' => 'LRY-002', 'name' => 'Tan Wei Ming', 'phone' => '0123001002', 'username' => 'driver2'],
            ['lorry_number' => 'LRY-003', 'name' => 'Kumar Raj', 'phone' => '0123001003', 'username' => 'driver3'],
        ];

        $drivers = [];
        foreach ($rows as $row) {
            $drivers[] = Driver::updateOrCreate(
                ['username' => $row['username']],
                array_merge($row, [
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ])
            );
        }

        return $drivers;
    }

    private function seedCustomers(array $drivers): array
    {
        $defaults = [
            'password' => Hash::make('password'),
            'status' => User::$user_status['active'],
            'registration_completed_at' => now(),
            'billing_address' => '12 Jalan Pasar',
            'billing_postcode' => '50000',
            'billing_state' => 'Kuala Lumpur',
            'shipping_address' => '12 Jalan Pasar',
            'shipping_postcode' => '50000',
            'shipping_state' => 'Kuala Lumpur',
            'payment_method' => json_encode(['cod']),
            'price_permission' => true,
            'invoice_visibility' => true,
            'invoice_price_permission' => true,
        ];

        $rows = [
            [
                'name' => 'Ocean Fresh Restaurant',
                'email' => 'ocean@demo.test',
                'login_code' => 'DEMO-COD-001',
                'category' => 'restaurant',
                'customer_type' => 'cod',
                'credit_balance' => 0,
                'attn_name' => 'Mr. Lee',
                'attn_contact' => '0131111001',
                'default_driver_id' => $drivers[0]->id,
            ],
            [
                'name' => 'Harbour View Hotel',
                'email' => 'harbour@demo.test',
                'login_code' => 'DEMO-COD-002',
                'category' => 'hotel',
                'customer_type' => 'cod',
                'credit_balance' => 0,
                'attn_name' => 'Sarah Tan',
                'attn_contact' => '0131111002',
                'default_driver_id' => $drivers[1]->id,
            ],
            [
                'name' => 'Blue Ocean Trading',
                'email' => 'blueocean@demo.test',
                'login_code' => 'DEMO-CREDIT-001',
                'category' => 'wholesale',
                'customer_type' => 'credit',
                'credit_balance' => 500.00,
                'attn_name' => 'David Wong',
                'attn_contact' => '0131111003',
                'payment_method' => json_encode(['term']),
                'default_driver_id' => $drivers[0]->id,
            ],
            [
                'name' => 'Seaside Retail Mart',
                'email' => 'seaside@demo.test',
                'login_code' => 'DEMO-CREDIT-002',
                'category' => 'retail',
                'customer_type' => 'credit',
                'credit_balance' => 120.50,
                'attn_name' => 'Nurul Aina',
                'attn_contact' => '0131111004',
                'payment_method' => json_encode(['term', 'bank-transfer']),
                'default_driver_id' => $drivers[2]->id,
            ],
        ];

        $customers = [];
        foreach ($rows as $row) {
            $customers[] = User::updateOrCreate(
                ['login_code' => $row['login_code']],
                array_merge($defaults, $row)
            );
        }

        return $customers;
    }

    private function seedProducts(int $uomId): array
    {
        $items = [
            ['name' => 'Live Tiger Prawns', 'sku' => 'SF-PRAWN', 'price' => 85.00, 'weight' => 1],
            ['name' => 'Live Garoupa', 'sku' => 'SF-GAROUPA', 'price' => 120.00, 'weight' => 1],
            ['name' => 'Live Mud Crab', 'sku' => 'SF-CRAB', 'price' => 95.00, 'weight' => 1],
            ['name' => 'Live Squid', 'sku' => 'SF-SQUID', 'price' => 45.00, 'weight' => 1],
            ['name' => 'Live Clams', 'sku' => 'SF-CLAM', 'price' => 28.00, 'weight' => 1],
            ['name' => 'Fresh Salmon Fillet', 'sku' => 'SF-SALMON', 'price' => 68.00, 'weight' => 1],
            ['name' => 'Live Lobster', 'sku' => 'SF-LOBSTER', 'price' => 180.00, 'weight' => 1],
            ['name' => 'Fresh Scallops', 'sku' => 'SF-SCALLOP', 'price' => 55.00, 'weight' => 1],
        ];

        $products = [];
        foreach ($items as $item) {
            $product = Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'uom_id' => $uomId,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'status' => Product::$status['active'],
                    'show_weight' => 1,
                    'show_qty' => 0,
                    'sell_in' => 'weight',
                ]
            );

            DB::table('product_stocks')->updateOrInsert(
                ['product_id' => $product->id],
                [
                    'quantity' => 100,
                    'weight' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $products[] = $product;
        }

        return $products;
    }

    private function seedProductVisibilities(array $customers, array $products): void
    {
        foreach ($customers as $customer) {
            foreach ($products as $product) {
                DB::table('product_visibilities')->updateOrInsert(
                    [
                        'user_id' => $customer->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedDeliverySlots(): array
    {
        $slots = [];
        foreach ([0, 1, 2] as $dayOffset) {
            $date = now()->addDays($dayOffset)->toDateString();
            foreach ([
                ['08:00:00', '11:00:00'],
                ['14:00:00', '17:00:00'],
            ] as [$start, $end]) {
                $id = DB::table('delivery_slots')->insertGetId([
                    'slot_date' => $date,
                    'time_start' => $start,
                    'time_end' => $end,
                    'max_orders' => 20,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $slots[] = (object) ['id' => $id, 'slot_date' => $date, 'time_start' => $start, 'time_end' => $end];
            }
        }

        return $slots;
    }

    private function seedOrders(array $customers, array $drivers, array $products, array $slots): void
    {
        if (Order::count() > 0) {
            return;
        }

        $slot = $slots[0];
        $timeSlot = substr($slot->time_start, 0, 5) . ' - ' . substr($slot->time_end, 0, 5);

        $definitions = [
            [
                'customer' => $customers[0],
                'driver' => $drivers[0],
                'status' => Order::$status['pending'],
                'payment_status' => Order::$payment_status['unpaid'],
                'items' => [
                    ['product' => $products[0], 'weight' => 2.5, 'unit_price' => 85.00],
                    ['product' => $products[2], 'weight' => 1.0, 'unit_price' => 95.00],
                ],
            ],
            [
                'customer' => $customers[1],
                'driver' => $drivers[1],
                'status' => Order::$status['in_route'],
                'payment_status' => Order::$payment_status['unpaid'],
                'items' => [
                    ['product' => $products[1], 'weight' => 3.0, 'unit_price' => 120.00],
                ],
            ],
            [
                'customer' => $customers[2],
                'driver' => $drivers[0],
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['payment_due'],
                'items' => [
                    ['product' => $products[3], 'weight' => 4.0, 'unit_price' => 45.00],
                    ['product' => $products[4], 'weight' => 2.0, 'unit_price' => 28.00],
                ],
            ],
            [
                'customer' => $customers[3],
                'driver' => $drivers[2],
                'status' => Order::$status['delivered'],
                'payment_status' => Order::$payment_status['paid'],
                'paid_amount' => 236.00,
                'items' => [
                    ['product' => $products[5], 'weight' => 2.0, 'unit_price' => 68.00],
                    ['product' => $products[6], 'weight' => 1.0, 'unit_price' => 180.00],
                ],
            ],
        ];

        foreach ($definitions as $index => $def) {
            $customer = $def['customer'];
            $subtotal = 0;

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
                'shipping_address' => $customer->shipping_address,
                'shipping_postcode' => $customer->shipping_postcode,
                'shipping_state' => $customer->shipping_state,
                'payment_method' => $customer->isCreditCustomer() ? 'term' : 'cod',
                'payment_status' => $def['payment_status'],
                'paid_amount' => $def['paid_amount'] ?? 0,
                'status' => $def['status'],
                'driver_id' => $def['driver']->id,
                'delivery_slot_id' => $slot->id,
                'delivery_date' => $slot->slot_date,
                'delivery_time_slot' => $timeSlot,
                'do_no' => 'DO-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'do_date' => now()->toDateString(),
            ]);

            foreach ($def['items'] as $item) {
                $lineTotal = round($item['weight'] * $item['unit_price'], 2);
                DB::table('order_products')->insert([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'quantity' => 0,
                    'weight' => $item['weight'],
                    'unit_price' => $item['unit_price'],
                    'price' => $lineTotal,
                    'product_weight' => $item['product']->weight,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
