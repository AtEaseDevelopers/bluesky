<?php

namespace Tests\Feature;

use App\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(array $attrs = []): Admin
    {
        return Admin::forceCreate(array_merge([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ], $attrs));
    }

    /** @test */
    public function admin_can_log_in_with_valid_credentials()
    {
        $admin = $this->makeAdmin(['username' => 'bossadmin']);

        $response = $this->post(route('admin.login.submit'), [
            'username' => 'bossadmin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(auth()->guard('web_admin')->check());
    }

    /** @test */
    public function authenticated_admin_can_load_dashboard()
    {
        $admin = $this->makeAdmin(['name' => 'Boss Lady']);

        $response = $this->actingAs($admin, 'web_admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Boss Lady');
    }

    /** @test */
    public function admin_login_fails_with_wrong_password()
    {
        $this->makeAdmin(['username' => 'bossadmin']);

        $response = $this->from(route('admin.login'))->post(route('admin.login.submit'), [
            'username' => 'bossadmin',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('error');
        $this->assertFalse(auth()->guard('web_admin')->check());
    }
}
