<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\RevenueMonsterTransaction;
use App\Services\RevenueMonster\RevenueMonsterService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AdminOrderQrTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeOrder(array $attrs = []): Order
    {
        $customer = User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);

        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'processing',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    private function mockRm(): void
    {
        $mock = Mockery::mock(RevenueMonsterService::class);
        $mock->shouldReceive('createWebPayment')->andReturn((object) [
            'checkoutId' => 'CHK-9',
            'url' => 'https://rm.test/pay?code=CHK-9',
        ]);
        $this->instance(RevenueMonsterService::class, $mock);
    }

    /** @test */
    public function admin_generate_creates_transaction_and_redirects_to_qr(): void
    {
        $this->mockRm();
        $admin = $this->makeAdmin();
        $order = $this->makeOrder();

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.qr-generate', $order->id))
            ->assertRedirect(route('admin.orders.qr', $order->id));

        $this->assertDatabaseHas('revenue_monster_transactions', [
            'order_id' => $order->id,
            'checkout_id' => 'CHK-9',
            'amount' => '150.00',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function admin_qr_page_displays_the_pending_checkout(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder();
        RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-SHOW',
            'checkout_id' => 'CHK-9',
            'qr_code_url' => 'https://rm.test/pay?code=CHK-9',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        // GET display never calls RM — it renders the existing checkout only.
        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.qr', $order->id))
            ->assertOk()
            ->assertSee('RM 150.00');
    }

    /** @test */
    public function fully_paid_order_redirects_to_summary(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder(['paid_amount' => 150.00, 'payment_status' => 'paid']);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.qr-generate', $order->id))
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $this->assertDatabaseCount('revenue_monster_transactions', 0);
    }

    /** @test */
    public function qr_status_endpoint_reports_paid_state(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder();

        $this->actingAs($admin, 'web_admin')
            ->getJson(route('admin.orders.qr-status', $order->id))
            ->assertOk()
            ->assertJson(['paid' => false, 'balance' => '150.00']);

        $order->update(['paid_amount' => 150.00]);

        $this->actingAs($admin, 'web_admin')
            ->getJson(route('admin.orders.qr-status', $order->id))
            ->assertOk()
            ->assertJson(['paid' => true, 'balance' => '0.00']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
