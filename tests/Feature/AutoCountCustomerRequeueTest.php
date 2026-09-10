<?php

namespace Tests\Feature;

use App\Services\AutoCountSyncService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutoCountCustomerRequeueTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): AutoCountSyncService
    {
        return app(AutoCountSyncService::class);
    }

    protected function makeSyncedCustomer(array $attrs = []): User
    {
        // forceCreate to satisfy NOT NULL columns regardless of $fillable.
        return User::forceCreate(array_merge([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
            'sql_customer_code' => '300-A' . rand(1000, 9999),
            'registration_completed_at' => now(),
            'autocount_sync_status' => 'synced',
            'autocount_synced_at' => now(),
        ], $attrs));
    }

    /** @test */
    public function it_requeues_a_synced_customer_when_a_synced_field_changes()
    {
        $customer = $this->makeSyncedCustomer();

        $result = $this->service()->requeueForDetailChange($customer, ['billing_address', 'name']);

        $this->assertNotNull($result);
        $this->assertSame('pending_sync', $customer->fresh()->autocount_sync_status);
        $this->assertNull($customer->fresh()->autocount_synced_at);
    }

    /** @test */
    public function it_skips_when_only_non_synced_fields_change()
    {
        $customer = $this->makeSyncedCustomer();

        // payment_method (JSON col), remark, price_permission are not pushed to AutoCount.
        $result = $this->service()->requeueForDetailChange($customer, ['payment_method', 'remark', 'price_permission']);

        $this->assertNull($result);
        $this->assertSame('synced', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_skips_when_nothing_changed()
    {
        $customer = $this->makeSyncedCustomer();

        $result = $this->service()->requeueForDetailChange($customer, []);

        $this->assertNull($result);
        $this->assertSame('synced', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_does_not_clobber_a_pending_inactive_customer()
    {
        // A deactivated customer awaiting inactive-sync must not be flipped back to pending_sync.
        $customer = $this->makeSyncedCustomer([
            'status' => 'inactive',
            'autocount_sync_status' => 'pending_inactive',
        ]);

        $result = $this->service()->requeueForDetailChange($customer, ['billing_address']);

        $this->assertNull($result);
        $this->assertSame('pending_inactive', $customer->fresh()->autocount_sync_status);
    }

    /** @test */
    public function it_skips_customers_who_have_not_completed_registration()
    {
        $customer = $this->makeSyncedCustomer([
            'registration_completed_at' => null,
            'autocount_sync_status' => 'pending',
        ]);

        $result = $this->service()->requeueForDetailChange($customer, ['name']);

        $this->assertNull($result);
        $this->assertSame('pending', $customer->fresh()->autocount_sync_status);
    }
}
