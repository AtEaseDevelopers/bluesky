<?php

namespace Tests\Feature;

use App\Admin;
use App\Driver;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverSoftDeleteTest extends TestCase
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

    private function makeDriver(array $attrs = []): Driver
    {
        return Driver::create(array_merge([
            'name' => 'John Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attrs));
    }

    private function makeOrderFor(Driver $driver): Order
    {
        $customer = User::forceCreate([
            'name' => 'Acme',
            'email' => 'c' . rand(1000, 9999) . '@example.com',
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

        return Order::forceCreate([
            'user_id' => $customer->id,
            'total_price' => 150.00,
            'status' => 'processing',
            'payment_method' => 'cash',
            'driver_id' => $driver->id,
            'fulfillment_type' => Order::$fulfillment_types['delivery'],
        ]);
    }

    /** @test */
    public function destroy_soft_deletes_the_driver_and_revokes_its_api_token()
    {
        $admin = $this->makeAdmin();
        $driver = $this->makeDriver();
        $driver->issueApiToken();

        $this->actingAs($admin, 'web_admin')
            ->delete(route('admin.drivers.destroy', encrypt($driver->id)))
            ->assertRedirect(route('admin.drivers.index'))
            ->assertSessionHas('success');

        // Row is retained (recoverable), not physically removed.
        $this->assertDatabaseHas('drivers', ['id' => $driver->id]);
        $this->assertNotNull(Driver::withTrashed()->find($driver->id)->deleted_at);
        // Default scope hides it.
        $this->assertNull(Driver::find($driver->id));
        // Access is revoked.
        $this->assertNull(Driver::withTrashed()->find($driver->id)->api_token);
    }

    /** @test */
    public function a_soft_deleted_driver_is_excluded_from_the_admin_list_and_select_options()
    {
        $admin = $this->makeAdmin();
        $keep = $this->makeDriver(['name' => 'Keep Me']);
        $gone = $this->makeDriver(['name' => 'Delete Me']);
        $gone->delete();

        $this->assertArrayHasKey($keep->id, Driver::optionsForSelect());
        $this->assertArrayNotHasKey($gone->id, Driver::optionsForSelect());

        // fetch() echoes JSON directly, so capture the output buffer.
        ob_start();
        $this->actingAs($admin, 'web_admin')
            ->post('/admin/fetch-drivers', ['draw' => 1, 'start' => 0, 'length' => 100])
            ->assertOk();
        $json = ob_get_clean();

        $this->assertStringContainsString('Keep Me', $json);
        $this->assertStringNotContainsString('Delete Me', $json);
        $this->assertStringContainsString('"recordsTotal":1', $json);
    }

    /** @test */
    public function a_soft_deleted_driver_still_authenticates_nowhere()
    {
        $driver = $this->makeDriver();
        $plain = $driver->issueApiToken();
        $driver->delete();

        $this->assertNull(Driver::findByToken($plain));
    }

    /** @test */
    public function past_orders_keep_the_deleted_driver_name()
    {
        $driver = $this->makeDriver(['name' => 'Historic Driver']);
        $order = $this->makeOrderFor($driver);
        $driver->delete();

        // Referenced-driver lookups used by order views must include trashed drivers.
        $label = Driver::displayLabelForId((int) $order->driver_id);
        $this->assertNotNull($label);
        $this->assertStringContainsString('Historic Driver', $label);

        $options = Driver::optionsForOrders([$order->driver_id]);
        $this->assertArrayHasKey($driver->id, $options);
        $this->assertStringContainsString(__('drivers.status_labels.deleted'), $options[$driver->id]);
    }
}
