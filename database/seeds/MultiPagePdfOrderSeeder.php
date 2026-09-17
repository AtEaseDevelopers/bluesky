<?php

use App\Driver;
use App\Order;
use App\Product;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo orders with enough line items to produce 2+ page invoices / delivery orders.
 *
 *   php artisan db:seed --class=MultiPagePdfOrderSeeder
 */
class MultiPagePdfOrderSeeder extends Seeder
{
    private const DO_PREFIX = 'PDF-PAGE-';

    public function run(): void
    {
        $driver = Driver::where('username', 'driver1')->first();
        $customer = User::where('email', 'ocean@demo.test')->first();

        if (!$driver || !$customer) {
            $this->command->error('Run DemoDataSeeder first (ocean@demo.test + driver1).');

            return;
        }

        $uomId = (int) DB::table('uoms')->where('uom_name', 'KG')->value('id');
        if (!$uomId) {
            $this->command->error('KG UOM missing. Run DemoDataSeeder first.');

            return;
        }

        $products = $this->ensurePdfLineProducts($uomId, $customer);
        $slotId = DB::table('delivery_slots')->value('id');
        $timeSlot = '09:00 - 12:00';
        $deliveryDate = now()->addDay()->toDateString();

        $definitions = [
            [
                'do_no' => self::DO_PREFIX . '2P',
                'line_count' => 12,
                'items' => null,
            ],
            [
                'do_no' => self::DO_PREFIX . '3P',
                'line_count' => 22,
                'items' => null,
            ],
            [
                'do_no' => self::DO_PREFIX . 'WRAP',
                'line_count' => 0,
                'items' => $this->buildWrapDemoLineItems($products),
            ],
            [
                'do_no' => self::DO_PREFIX . '1MAX',
                'line_count' => 0,
                'items' => $this->buildOnePageMaxLineItems($products),
            ],
        ];

        DB::transaction(function () use ($definitions, $customer, $driver, $products, $slotId, $timeSlot, $deliveryDate) {
            foreach ($definitions as $def) {
                $items = $def['items'] ?? $this->buildLineItems($products, $def['line_count']);
                $subtotal = round(collect($items)->sum('price'), 2);

                $order = Order::updateOrCreate(
                    ['do_no' => $def['do_no']],
                    [
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
                        'payment_method' => 'cod',
                        'payment_status' => Order::$payment_status['unpaid'],
                        'paid_amount' => 0,
                        'status' => Order::$status['pending'],
                        'fulfillment_type' => Order::$fulfillment_types['delivery'],
                        'driver_id' => $driver->id,
                        'delivery_slot_id' => $slotId,
                        'delivery_date' => $deliveryDate,
                        'delivery_time_slot' => $timeSlot,
                        'do_date' => now()->toDateString(),
                        'invoice_number' => 'INV-' . $def['do_no'],
                    ]
                );

                DB::table('order_products')->where('order_id', $order->id)->delete();
                foreach ($items as $item) {
                    DB::table('order_products')->insert(array_merge($item, [
                        'order_id' => $order->id,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        });

        $this->command->info('Multi-page PDF demo orders seeded.');
        $this->command->line('Admin → Manage Orders → search "PDF-PAGE"');
        $this->command->line('  PDF-PAGE-2P — 12 lines (~2 pages with prices)');
        $this->command->line('  PDF-PAGE-3P — 22 lines (~3 pages with prices)');
        $this->command->line('  PDF-PAGE-WRAP — 10 lines with long names/remarks (wrap test, 1 page target)');
        $this->command->line('  PDF-PAGE-1MAX — 10 short lines (max one-page priced DO + footer)');
        $this->command->line('Customer: Ocean Fresh (ocean@demo.test) | Driver: Ahmad Rizal (driver1)');
        $this->command->line('Priced DO/INV: up to 10 line items per page (+ footer on last page only).');
    }

    /** @return \Illuminate\Support\Collection<int, Product> */
    private function ensurePdfLineProducts(int $uomId, User $customer)
    {
        $names = [
            'PDF Demo Tiger Prawn',
            'PDF Demo Grouper',
            'PDF Demo Squid',
            'PDF Demo Clams',
            'PDF Demo Crab',
            'PDF Demo Seabass',
            'PDF Demo Mackerel',
            'PDF Demo Oyster',
        ];

        $products = collect();
        foreach ($names as $i => $name) {
            $sku = 'PDF-DEMO-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $price = 40.00 + ($i * 5);
            $product = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'uom_id' => $uomId,
                    'name' => $name,
                    'price' => $price,
                    'weight' => 1,
                    'status' => Product::$status['active'],
                    'show_weight' => 1,
                    'show_qty' => 1,
                    'sell_in' => 'qty_bill_weight',
                ]
            );

            DB::table('product_stocks')->updateOrInsert(
                ['product_id' => $product->id],
                [
                    'quantity' => 500,
                    'weight' => 500,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('product_visibilities')->updateOrInsert(
                ['user_id' => $customer->id, 'product_id' => $product->id],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $products->push($product);
        }

        return $products;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function buildLineItems($products, int $lineCount): array
    {
        $items = [];
        for ($i = 0; $i < $lineCount; $i++) {
            /** @var Product $product */
            $product = $products[$i % $products->count()];
            $weight = round(1 + ($i % 5) * 0.5, 1);
            $unitPrice = (float) $product->price;
            $lineTotal = round($weight * $unitPrice, 2);

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 0,
                'weight' => $weight,
                'unit_price' => $unitPrice,
                'price' => $lineTotal,
                'product_weight' => $product->weight ?? 1,
            ];
        }

        return $items;
    }

    /**
     * Ten lines on one priced page: short rows plus long product names and line remarks
     * to exercise description wrapping (same line number, taller cell — not a new row).
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function buildWrapDemoLineItems($products): array
    {
        $longName = 'FRESH NORWEGIAN SALMON FILLET (SASHIMI GRADE) / 挪威新鲜三文鱼扒 '
            . '— CUT TO ORDER FOR HOTEL BUFFET, SKIN ON, PIN BONE OUT, ICE PACK REQUIRED';
        $longRemark = 'Pack on ice separately from shellfish; deliver before 10:00 via loading bay B; '
            . 'call chef Ahmad (ext 402) if gate is closed.';

        $specs = [
            ['short' => true],
            ['name' => $longName],
            ['remark' => $longRemark],
            ['short' => true],
            ['name' => 'LIVE AUSTRALIAN LOBSTER (1.2–1.5KG EACH) / 澳洲活龙虾 — AIR-FREIGHT, HANDLE WITH CARE, DO NOT STACK CRATES'],
            ['short' => true],
            ['name' => 'PREMIUM DRIED SCALLOP (JAPANESE SIZE L) / 日本大元贝 — SOAK 30 MINUTES BEFORE USE PER ATTACHED RECIPE'],
            ['remark' => 'Split into two bags: 3kg for main kitchen, 2kg for banquet prep.'],
            ['short' => true],
            ['name' => $longName, 'remark' => 'Urgent: replace last week short delivery if possible.'],
        ];

        $items = [];
        foreach ($specs as $i => $spec) {
            /** @var Product $product */
            $product = $products[$i % $products->count()];
            $weight = 2.0 + ($i * 0.3);
            $unitPrice = (float) $product->price;
            $lineTotal = round($weight * $unitPrice, 2);

            $row = [
                'product_id' => $product->id,
                'product_name' => $spec['name'] ?? $product->name,
                'quantity' => 0,
                'weight' => round($weight, 1),
                'unit_price' => $unitPrice,
                'price' => $lineTotal,
                'product_weight' => $product->weight ?? 1,
                'remark' => $spec['remark'] ?? null,
            ];
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Ten compact lines — the template maximum for a priced page with header + footer.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function buildOnePageMaxLineItems($products): array
    {
        $shortNames = [
            'Tiger Prawn M',
            'Grouper Fillet',
            'Squid Tube',
            'Fresh Clams',
            'Blue Crab',
            'Seabass Whole',
            'Mackerel',
            'Oyster Dozen',
            'Salmon Steak',
            'Live Lobster S',
        ];

        $items = [];
        foreach ($shortNames as $i => $label) {
            /** @var Product $product */
            $product = $products[$i % $products->count()];
            $weight = 1.0 + ($i * 0.2);
            $unitPrice = (float) $product->price;
            $lineTotal = round($weight * $unitPrice, 2);

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $label,
                'quantity' => 0,
                'weight' => round($weight, 1),
                'unit_price' => $unitPrice,
                'price' => $lineTotal,
                'product_weight' => $product->weight ?? 1,
                'remark' => null,
            ];
        }

        return $items;
    }
}
