<?php

namespace Tests\Feature;

use App\Admin;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCustomerEditRendersTest extends TestCase
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

    /**
     * @test
     * The customer edit page must render even when payment_method is not an
     * array (null / legacy bare string). It previously crashed on an in_array()
     * call inside dead, HTML-commented Blade that still compiled.
     */
    public function customer_edit_renders_when_payment_method_is_not_an_array(): void
    {
        $admin = $this->makeAdmin();

        foreach ([null, 'credit-term'] as $paymentMethod) {
            $customer = User::forceCreate([
                'name' => 'Legacy Customer',
                'email' => 'legacy' . rand(1000, 999999) . '@example.com',
                'password' => Hash::make('password'),
                'category' => 'credit',
                'customer_type' => 'credit',
                'credit_balance' => 0,
                'status' => 'active',
                'registration_completed_at' => now(),
                'payment_method' => $paymentMethod,
                'login_code' => 'code' . rand(1000, 999999),
                'billing_address' => '1 Market St',
                'billing_postcode' => '50000',
                'billing_state' => 'WP',
                'shipping_address' => '1 Market St',
                'shipping_postcode' => '50000',
                'shipping_state' => 'WP',
            ]);

            $this->actingAs($admin, 'web_admin')
                ->get(route('admin.customers.edit', encrypt($customer->id)))
                ->assertOk();
        }
    }
}
