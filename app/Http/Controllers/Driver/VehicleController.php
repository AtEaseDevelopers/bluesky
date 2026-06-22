<?php

namespace App\Http\Controllers\Driver;

use App\Driver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Lets a driver choose which lorry (vehicle) they are currently operating.
 *
 * In this system a driver's vehicle is stored as `drivers.lorry_number`, which
 * the office sees on every delivery the driver handles. The driver may only
 * pick from lorries already registered in the fleet — never free-form text.
 */
class VehicleController extends Controller
{
    /**
     * Show the vehicle selection page with the registered fleet.
     */
    public function edit()
    {
        $driver = Auth::guard('web_driver')->user();

        return view('driver.vehicle.edit', [
            'lorries' => $this->availableLorries(),
            'current' => $driver->lorry_number,
            'assigned' => $this->lorriesAssignedToOthers($driver),
        ]);
    }

    /**
     * Persist the driver's chosen lorry to their profile.
     */
    public function update(Request $request)
    {
        $driver = Auth::guard('web_driver')->user();
        $lorries = $this->availableLorries();

        $data = $request->validate([
            'lorry_number' => ['required', 'string', Rule::in($lorries->all())],
        ], [
            'lorry_number.required' => 'Please choose a vehicle.',
            'lorry_number.in' => 'The selected vehicle is not registered. Please pick one from the list.',
        ]);

        $driver->update(['lorry_number' => $data['lorry_number']]);

        return redirect(route('driver.vehicle.edit'))
            ->with('success', 'Your vehicle is now ' . $data['lorry_number'] . '.');
    }

    /**
     * Distinct, non-empty lorry numbers registered across the fleet.
     */
    private function availableLorries(): Collection
    {
        return Driver::query()
            ->whereNotNull('lorry_number')
            ->where('lorry_number', '!=', '')
            ->orderBy('lorry_number')
            ->pluck('lorry_number')
            ->unique()
            ->values();
    }

    /**
     * Lorry numbers currently held by another active driver — these are not
     * free for the given driver to claim.
     */
    private function lorriesAssignedToOthers(Driver $driver): Collection
    {
        return Driver::query()
            ->where('id', '!=', $driver->id)
            ->where('is_active', true)
            ->whereNotNull('lorry_number')
            ->where('lorry_number', '!=', '')
            ->pluck('lorry_number')
            ->unique()
            ->values();
    }
}
