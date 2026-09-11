<?php

namespace Tests\Feature;

use App\Admin;
use App\Role;
use App\Services\RolePermissionService;
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

    /**
     * Create an admin-portal role granted only the given flat permission keys.
     */
    protected function makeRole(string $slug, array $enabledPermissions): Role
    {
        $role = Role::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'portal' => Role::PORTAL_ADMIN,
            'is_system' => false,
            'is_superadmin' => false,
        ]);

        app(RolePermissionService::class)->sync($role, $enabledPermissions);

        return $role;
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
    public function admin_without_dashboard_access_is_redirected_to_first_accessible_module_on_login()
    {
        $this->makeRole('ops', ['orders.view', 'orders.create', 'orders.edit']);
        $this->makeAdmin(['username' => 'opsuser', 'role' => 'ops']);

        $response = $this->post(route('admin.login.submit'), [
            'username' => 'opsuser',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.orders'));
        $this->assertTrue(auth()->guard('web_admin')->check());
    }

    /** @test */
    public function admin_without_dashboard_access_hitting_dashboard_directly_still_gets_403()
    {
        $this->makeRole('ops', ['orders.view']);
        $admin = $this->makeAdmin(['username' => 'opsuser2', 'role' => 'ops']);

        $response = $this->actingAs($admin, 'web_admin')->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    /** @test */
    public function admin_root_redirects_to_first_accessible_module()
    {
        $this->makeRole('ops', ['orders.view']);
        $admin = $this->makeAdmin(['username' => 'opsuser3', 'role' => 'ops']);

        $response = $this->actingAs($admin, 'web_admin')->get('/admin');

        $response->assertRedirect(route('admin.orders'));
    }

    /** @test */
    public function admin_with_no_accessible_module_is_logged_out_with_error()
    {
        $this->makeRole('norole', []);
        $this->makeAdmin(['username' => 'norolelogin', 'role' => 'norole']);

        $response = $this->post(route('admin.login.submit'), [
            'username' => 'norolelogin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('error');
        $this->assertFalse(auth()->guard('web_admin')->check());
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
