<?php

use App\Driver;
use App\Order;
use App\Product;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds one "in route" delivery order per active driver (for driver portal testing).
 *
 * Idempotent — skips drivers that already have an in-route/delivering order, or
 * an order with do_no like DRV-ROUTE-{id}-%.
 *
 *   php artisan db:seed --class=DriverInRouteSeeder
 */
class DriverInRouteSeeder extends Seeder
{
    private const DO_PREFIX = 'DRV-ROUTE-';

    public function run(): void
    {
        $drivers = Driver::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($drivers->isEmpty()) {
            $this->log('No active drivers found.');

            return;
        }

        $customers = User::query()
            ->where('role_slug', 'customer')
            ->orderBy('id')
            ->get();

        if ($customers->isEmpty()) {
            $this->log('No customers found. Create at least one customer first.');

            return;
        }

        $product = Product::query()
            ->where('status', '!=', Product::$status['removed'])
            ->orderBy('id')
            ->first();

        if (!$product) {
            $this->log('No products found. Create at least one product first.');

            return;
        }

        $slotId = DB::table('delivery_slots')->value('id');
        $deliveryDate = now()->toDateString();
        $timeSlot = '09:00 - 12:00';
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $drivers,
            $customers,
            $product,
            $slotId,
            $deliveryDate,
            $timeSlot,
            &$created,
            &$skipped
        ) {
            foreach ($drivers as $index => $driver) {
                $doNo = self::DO_PREFIX . $driver->id;

                $alreadyHasRouteOrder = Order::query()
                    ->where('driver_id', $driver->id)
                    ->where(function ($query) use ($doNo) {
                        $query->where('do_no', 'like', self::DO_PREFIX . '%')
                            ->orWhereIn('status', [
                                Order::$status['in_route'],
                                'delivering',
                            ]);
                    })
                    ->exists();

                if ($alreadyHasRouteOrder) {
                    $skipped++;
                    continue;
                }

                /** @var User $customer */
                $customer = $customers[$index % $customers->count()];

                $weight = 2.0;
                $unitPrice = (float) $product->price;
                $subtotal = round($weight * $unitPrice, 2);

                $order = Order::create([
                    'user_id' => $customer->id,
                    'order_type' => Order::$order_types['registered'],
                    'total_price' => $subtotal,
                    'subtotal' => $subtotal,
                    'delivery_fee' => 0,
                    'attn_name' => $customer->attn_name ?: $customer->name,
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
                    'driver_assigned_at' => now(),
                    'delivery_slot_id' => $slotId,
                    'delivery_date' => $deliveryDate,
                    'delivery_time_slot' => $timeSlot,
                    'do_no' => $doNo,
                    'do_date' => $deliveryDate,
                    'is_estimated' => false,
                ]);

                $lineTotal = round($weight * $unitPrice, 2);

                DB::table('order_products')->insert([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => 0,
                    'weight' => $weight,
                    'unit_price' => $unitPrice,
                    'price' => $lineTotal,
                    'product_weight' => $product->weight ?? 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
                $this->log(sprintf(
                    'Created %s for driver %s (%s) → customer %s',
                    $doNo,
                    $driver->name,
                    $driver->username,
                    $customer->name
                ));
            }
        });

        $this->log("Done. Created {$created} in-route order(s), skipped {$skipped} driver(s).");
    }

    private function log(string $message): void
    {
        if ($this->command) {
            $this->command->line($message);
        }
    }
}
