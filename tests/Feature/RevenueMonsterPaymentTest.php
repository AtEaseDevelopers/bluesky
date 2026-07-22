<?php

namespace Tests\Feature;

use App\Driver;
use App\Order;
use App\OrderPayment;
use App\RevenueMonsterTransaction;
use App\Services\RevenueMonster\RevenueMonsterService;
use App\Services\RevenueMonster\RevenueMonsterSignature;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RevenueMonsterPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($resource)['key'];

        // The app verifies callbacks with RM's public key; sign test callbacks
        // with the matching private key to imitate RM.
        config([
            'revenuemonster.public_key' => $this->publicKey,
            'revenuemonster.private_key' => $this->privateKey,
            'revenuemonster.sandbox' => true,
        ]);

        Cache::flush(); // avoid the reconcile throttle bleeding across tests
    }

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'John Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
            'role_slug' => 'driver',
        ]);
    }

    private function makeOrder(Driver $driver, array $attrs = []): Order
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
            'status' => 'in_route',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'driver_id' => $driver->id,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function driver_generate_creates_transaction_and_redirects_to_qr(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        $mock = Mockery::mock(RevenueMonsterService::class);
        $mock->shouldReceive('createWebPayment')->once()->andReturn((object) [
            'checkoutId' => 'CHK-1',
            'url' => 'https://rm.test/pay?code=CHK-1',
        ]);
        $this->instance(RevenueMonsterService::class, $mock);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.rm-pay', $order->id))
            ->assertRedirect(route('driver.orders.rm-qr', $order->id));

        $this->assertDatabaseHas('revenue_monster_transactions', [
            'order_id' => $order->id,
            'checkout_id' => 'CHK-1',
            'amount' => '150.00',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function driver_qr_page_displays_the_pending_checkout(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);
        RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-SHOW',
            'checkout_id' => 'CHK-2',
            'qr_code_url' => 'https://rm.test/pay?code=CHK-2',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        // GET display never calls RM — it only renders the existing checkout.
        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.orders.rm-qr', $order->id))
            ->assertOk()
            ->assertSee('RM 150.00');
    }

    /** @test */
    public function stale_pending_checkout_is_not_reused(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        // A pending checkout left over from an earlier session / integration.
        $stale = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-STALE',
            'checkout_id' => 'OLD',
            'qr_code_url' => 'https://sb-api.revenuemonster.my/payment/unified?code=OLD',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);
        $stale->forceFill(['created_at' => now()->subMinutes(120)])->save();

        $mock = Mockery::mock(RevenueMonsterService::class);
        $mock->shouldReceive('createWebPayment')->once()->andReturn((object) [
            'checkoutId' => 'NEW',
            'url' => 'https://sb-pg.revenuemonster.my/v4/checkout?checkoutId=NEW',
        ]);
        $this->instance(RevenueMonsterService::class, $mock);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.rm-pay', $order->id))
            ->assertRedirect(route('driver.orders.rm-qr', $order->id));

        // A fresh checkout was created rather than reusing the stale one.
        $this->assertDatabaseHas('revenue_monster_transactions', [
            'order_id' => $order->id,
            'checkout_id' => 'NEW',
            'status' => 'pending',
        ]);
        $this->assertSame(2, RevenueMonsterTransaction::where('order_id', $order->id)->count());
    }

    /** @test */
    public function driver_cannot_generate_below_the_minimum_amount(): void
    {
        config(['revenuemonster.min_amount' => 1]);
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['total_price' => 0.50]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.rm-pay', $order->id))
            ->assertRedirect(route('driver.orders.show', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('revenue_monster_transactions', 0);
    }

    /** @test */
    public function driver_cannot_generate_when_nothing_is_due(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['paid_amount' => 150.00, 'payment_status' => 'paid']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.rm-pay', $order->id))
            ->assertRedirect(route('driver.orders.show', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('revenue_monster_transactions', 0);
    }

    /** @test */
    public function webhook_records_payment_and_is_idempotent(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        $transaction = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-ABC123',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        $body = [
            'data' => [
                'status' => 'SUCCESS',
                'transactionId' => 'TXN-999',
                'amount' => 15000,
                'order' => ['id' => $transaction->reference],
            ],
        ];

        // First callback records the payment.
        $this->postSignedWebhook($body)->assertOk();

        $order->refresh();
        $this->assertEqualsWithDelta(150.00, (float) $order->paid_amount, 0.001);
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => 'payment-gateway',
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        // Repeated callback (RM retry) must not double-charge.
        $this->postSignedWebhook($body)->assertOk();

        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEqualsWithDelta(150.00, (float) $order->fresh()->paid_amount, 0.001);
    }

    /** @test */
    public function webhook_records_payment_when_order_is_not_yet_out_for_delivery(): void
    {
        // "Pay now" up front: the QR is generated and paid while the order is
        // still pending (before it is out for delivery). A confirmed gateway
        // payment is authoritative and must still be recorded — otherwise the
        // money is collected but the order keeps showing "unpaid".
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['status' => 'pending']);

        $transaction = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-PREPAY',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        $this->postSignedWebhook([
            'data' => [
                'status' => 'SUCCESS',
                'transactionId' => 'TXN-PRE',
                'amount' => 15000,
                'order' => ['id' => $transaction->reference],
            ],
        ])->assertOk();

        $order->refresh();
        $this->assertEqualsWithDelta(150.00, (float) $order->paid_amount, 0.001);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
    }

    /** @test */
    public function webhook_rejects_an_invalid_signature(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-ABC123',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        $body = [
            'data' => [
                'status' => 'SUCCESS',
                'transactionId' => 'TXN-999',
                'order' => ['id' => 'RM' . $order->id . '-ABC123'],
            ],
        ];

        $this->postJson(route('webhooks.revenue-monster'), $body, [
            'X-Signature' => 'sha256 ' . base64_encode('bogus'),
            'X-Nonce-Str' => 'nonce',
            'X-Timestamp' => (string) time(),
        ])->assertStatus(401);

        $this->assertEqualsWithDelta(0.0, (float) $order->fresh()->paid_amount, 0.001);
    }

    /** @test */
    public function webhook_marks_transaction_failed_on_non_success(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        $transaction = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-DECLINE',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        $this->postSignedWebhook([
            'data' => [
                'status' => 'FAILED',
                'transactionId' => 'TXN-DEC',
                'order' => ['id' => $transaction->reference],
            ],
        ])->assertOk();

        $this->assertSame('failed', $transaction->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $order->fresh()->paid_amount, 0.001);
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)->count());

        // The QR status endpoint should now report the failure for that ref.
        $this->actingAs($driver, 'web_driver')
            ->getJson(route('driver.orders.rm-status', $order->id) . '?ref=' . $transaction->reference)
            ->assertOk()
            ->assertJson(['paid' => false, 'failed' => true]);
    }

    /** @test */
    public function webhook_fails_closed_when_public_key_is_unusable(): void
    {
        // Simulate an unconfigured / placeholder rm_public.pem.
        config(['revenuemonster.public_key' => 'not-a-valid-key']);
        $this->app->forgetInstance(\App\Services\RevenueMonster\RevenueMonsterClient::class);
        $this->app->forgetInstance(\App\Services\RevenueMonster\RevenueMonsterService::class);

        $order = $this->makeOrder($this->makeDriver());
        RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-KEY',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        // Fails closed with 401 (not a 500) even though the key can't verify.
        $this->postJson(route('webhooks.revenue-monster'), [
            'data' => ['status' => 'SUCCESS', 'order' => ['id' => 'RM' . $order->id . '-KEY']],
        ], [
            'X-Signature' => 'sha256 ' . base64_encode('x'),
            'X-Nonce-Str' => 'n',
            'X-Timestamp' => (string) time(),
        ])->assertStatus(401);

        $this->assertEqualsWithDelta(0.0, (float) $order->fresh()->paid_amount, 0.001);
    }

    /** @test */
    public function status_reconciles_from_gateway_when_webhook_is_missed(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);
        $txn = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-REC',
            'checkout_id' => 'CHK',
            'qr_code_url' => 'https://rm.test/pay?code=CHK',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        // RM's API confirms success even though no webhook arrived.
        $mock = Mockery::mock(RevenueMonsterService::class);
        $mock->shouldReceive('getTransactionByOrderId')->with($txn->reference)->andReturn((object) [
            'status' => 'SUCCESS',
            'transactionId' => 'TXN-REC',
            'order' => ['amount' => 15000],
        ]);
        $this->instance(RevenueMonsterService::class, $mock);

        $this->actingAs($driver, 'web_driver')
            ->getJson(route('driver.orders.rm-status', $order->id) . '?ref=' . $txn->reference)
            ->assertOk()
            ->assertJson(['paid' => true]);

        $this->assertEqualsWithDelta(150.0, (float) $order->fresh()->paid_amount, 0.001);
        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
    }

    /** @test */
    public function payment_return_page_shows_success_when_order_is_paid(): void
    {
        $order = $this->makeOrder($this->makeDriver(), ['paid_amount' => 150.00, 'payment_status' => 'paid']);
        $txn = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-RET',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'paid',
        ]);

        // DB (paid) overrides the gateway's redirect param.
        $this->get(route('rm.return', ['orderId' => $txn->reference, 'status' => 'pending']))
            ->assertOk()
            ->assertSee(__('orders.qr.return_success_title'));
    }

    /** @test */
    public function payment_return_page_shows_processing_when_unconfirmed(): void
    {
        $order = $this->makeOrder($this->makeDriver());
        $txn = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => 'RM' . $order->id . '-PEN',
            'amount' => '150.00',
            'currency' => 'MYR',
            'status' => 'pending',
        ]);

        $this->get(route('rm.return', ['orderId' => $txn->reference]))
            ->assertOk()
            ->assertSee(__('orders.qr.return_pending_title'));
    }

    private function postSignedWebhook(array $body)
    {
        $signer = new RevenueMonsterSignature($this->privateKey, $this->publicKey);
        $url = route('webhooks.revenue-monster');
        $nonce = 'nonce' . rand(1000, 9999);
        $timestamp = time(); // within the freshness window

        $signature = $signer->sign('post', $url, $nonce, $timestamp, $body);

        return $this->postJson($url, $body, [
            'X-Signature' => 'sha256 ' . $signature,
            'X-Nonce-Str' => $nonce,
            'X-Timestamp' => (string) $timestamp,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
