<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvertOrdersToWalkInTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name, ?string $contact = null): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => 'cust' . rand(1000, 999999) . '@example.com',
            'password' => Hash::make('password'),
            'attn_contact' => $contact,
            'status' => 'active',
        ]);
    }

    private function makeOrder(User $customer, string $createdAt): Order
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 20.00,
            'status' => Order::$status['completed'],
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        // Pin created_at without the model overwriting it on save.
        Order::where('id', $order->id)->update(['created_at' => $createdAt]);

        return $order->fresh();
    }

    /** @test */
    public function dry_run_previews_but_changes_nothing(): void
    {
        $c = $this->makeCustomer('Pavilion Hilltop', '0123456789');
        $order = $this->makeOrder($c, '2026-09-17 10:00:00');

        $this->artisan('orders:convert-to-walk-in', [
            '--date' => '2026-09-17',
            '--customer' => ['pavilion hilltop'],
            '--dry-run' => true,
        ])->assertExitCode(0);

        $order->refresh();
        $this->assertSame('registered', $order->order_type);
        $this->assertSame($c->id, $order->user_id);
        $this->assertNull($order->walk_in_name);
    }

    /** @test */
    public function it_converts_matching_orders_to_walk_in(): void
    {
        $pavilion = $this->makeCustomer('Pavilion Hilltop', '0111111111');
        $velocity = $this->makeCustomer('Velocity 2', '0122222222');
        $casaman = $this->makeCustomer('Casaman', '0133333333');

        $o1 = $this->makeOrder($pavilion, '2026-09-17 08:00:00');
        $o2 = $this->makeOrder($velocity, '2026-09-17 09:00:00');
        $o3 = $this->makeOrder($casaman, '2026-09-17 23:59:00');

        $this->artisan('orders:convert-to-walk-in', [
            '--date' => '2026-09-17',
            '--customer' => ['pavilion hilltop', 'velocity 2', 'casaman'],
        ])->assertExitCode(0);

        foreach ([[$o1, 'Pavilion Hilltop', '0111111111'], [$o2, 'Velocity 2', '0122222222'], [$o3, 'Casaman', '0133333333']] as [$order, $name, $phone]) {
            $order->refresh();
            $this->assertSame('walk_in', $order->order_type);
            $this->assertNull($order->user_id);
            $this->assertTrue((bool) $order->is_general);
            $this->assertSame($name, $order->walk_in_name);
            $this->assertSame($phone, $order->walk_in_phone);
        }
    }

    /** @test */
    public function it_ignores_other_dates_and_other_customers(): void
    {
        $pavilion = $this->makeCustomer('Pavilion Hilltop');
        $other = $this->makeCustomer('Some Other Shop');

        $wrongDate = $this->makeOrder($pavilion, '2026-09-16 10:00:00');
        $wrongCustomer = $this->makeOrder($other, '2026-09-17 10:00:00');

        $this->artisan('orders:convert-to-walk-in', [
            '--date' => '2026-09-17',
            '--customer' => ['pavilion hilltop', 'velocity 2', 'casaman'],
        ])->assertExitCode(0);

        $wrongDate->refresh();
        $wrongCustomer->refresh();
        $this->assertSame('registered', $wrongDate->order_type);
        $this->assertSame('registered', $wrongCustomer->order_type);
        $this->assertNotNull($wrongCustomer->user_id);
    }

    /** @test */
    public function id_mode_converts_exact_orders_ignoring_date(): void
    {
        $pavilion = $this->makeCustomer('Pavilion Hilltop', '0111111111');
        $velocity = $this->makeCustomer('Velocity 2', '0122222222');

        // Both placed just after midnight — wrong created_at date, right batch.
        $o1 = $this->makeOrder($pavilion, '2026-09-18 00:05:00');
        $o2 = $this->makeOrder($velocity, '2026-09-18 00:12:00');

        $this->artisan('orders:convert-to-walk-in', [
            '--id' => [(string) $o1->id, (string) $o2->id],
        ])->assertExitCode(0);

        $o1->refresh();
        $o2->refresh();
        $this->assertSame('walk_in', $o1->order_type);
        $this->assertNull($o1->user_id);
        $this->assertSame('Pavilion Hilltop', $o1->walk_in_name);
        $this->assertSame('walk_in', $o2->order_type);
        $this->assertSame('Velocity 2', $o2->walk_in_name);
    }

    /** @test */
    public function missing_date_fails(): void
    {
        $this->artisan('orders:convert-to-walk-in', [
            '--customer' => ['pavilion hilltop'],
        ])->assertExitCode(1);
    }
}
