<?php

namespace Tests\Feature;

use App\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverVehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDriver(array $attrs = []): Driver
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

    /** @test */
    public function guests_are_redirected_to_login_from_the_vehicle_page()
    {
        $this->get(route('driver.vehicle.edit'))
            ->assertRedirect(route('driver.login'));
    }

    /** @test */
    public function vehicle_page_lists_every_registered_lorry_and_highlights_the_current_one()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        $this->makeDriver(['lorry_number' => 'LRY-BBB']);
        $this->makeDriver(['lorry_number' => 'LRY-CCC']);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.vehicle.edit'))
            ->assertOk()
            ->assertSee('LRY-AAA')
            ->assertSee('LRY-BBB')
            ->assertSee('LRY-CCC');
    }

    /** @test */
    public function driver_can_switch_to_another_registered_lorry()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        // A registered lorry that no active driver currently holds is free to take.
        $this->makeDriver(['lorry_number' => 'LRY-BBB', 'is_active' => false]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => 'LRY-BBB'])
            ->assertRedirect(route('driver.vehicle.edit'))
            ->assertSessionHas('success');

        $this->assertEquals('LRY-BBB', $driver->fresh()->lorry_number);
    }

    /** @test */
    public function driver_cannot_choose_a_lorry_that_is_not_registered()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => 'GHOST-LORRY'])
            ->assertSessionHasErrors('lorry_number');

        // The driver's vehicle is left untouched.
        $this->assertEquals('LRY-AAA', $driver->fresh()->lorry_number);
    }

    /** @test */
    public function a_vehicle_must_be_selected()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => ''])
            ->assertSessionHasErrors('lorry_number');

        $this->assertEquals('LRY-AAA', $driver->fresh()->lorry_number);
    }

    /** @test */
    public function driver_cannot_select_a_lorry_assigned_to_another_active_driver()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        $this->makeDriver(['lorry_number' => 'LRY-BBB', 'is_active' => true]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => 'LRY-BBB'])
            ->assertSessionHasErrors('lorry_number');

        // The blocked switch leaves the driver on their original lorry.
        $this->assertEquals('LRY-AAA', $driver->fresh()->lorry_number);
    }

    /** @test */
    public function a_lorry_held_only_by_an_inactive_driver_is_free_to_claim()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        $this->makeDriver(['lorry_number' => 'LRY-BBB', 'is_active' => false]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => 'LRY-BBB'])
            ->assertRedirect(route('driver.vehicle.edit'))
            ->assertSessionHas('success');

        $this->assertEquals('LRY-BBB', $driver->fresh()->lorry_number);
    }

    /** @test */
    public function the_vehicle_page_marks_lorries_assigned_to_others_as_in_use()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        $this->makeDriver(['lorry_number' => 'LRY-BBB', 'is_active' => true]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.vehicle.edit'))
            ->assertOk()
            ->assertSee('in use');
    }

    /** @test */
    public function blank_and_null_lorry_numbers_are_excluded_from_the_choices()
    {
        $driver = $this->makeDriver(['lorry_number' => 'LRY-AAA']);
        $this->makeDriver(['lorry_number' => '']);
        $this->makeDriver(['lorry_number' => null]);

        // A driver whose record has a blank lorry number is not a selectable vehicle.
        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.vehicle.update'), ['lorry_number' => ''])
            ->assertSessionHasErrors('lorry_number');
    }
}
