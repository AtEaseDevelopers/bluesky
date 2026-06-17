<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Role;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(RolePermissionService $service)
    {
        $this->authorizeSuperadmin();

        return view('admin.roles.index', [
            'superadminRole' => Role::where('is_superadmin', true)->first(),
            'rolesByPortal' => $service->rolesByPortal(),
            'portals' => config('permissions.portals', []),
        ]);
    }

    public function create()
    {
        $this->authorizeSuperadmin();

        return view('admin.roles.create', [
            'portals' => config('permissions.portals', []),
            'portalPermissions' => $this->portalPermissionOptions(),
        ]);
    }

    public function store(Request $request, RolePermissionService $service)
    {
        $this->authorizeSuperadmin();

        $portals = array_keys(config('permissions.portals', []));
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'portal' => ['required', Rule::in($portals)],
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $validKeys = array_keys($service->portalDefinitions($data['portal']));
        $enabled = array_values(array_intersect($data['permissions'] ?? [], $validKeys));

        $role = $service->createRole($data, $enabled);

        return redirect()
            ->route('admin.roles.edit', $role->slug)
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        return redirect()->route('admin.roles.edit', $role);
    }

    public function edit(Role $role, RolePermissionService $service)
    {
        $this->authorizeSuperadmin();

        return view('admin.roles.edit', [
            'role' => $role,
            'permissions' => $service->definitionsForRole($role),
            'allowed' => $service->allowedMap($role->slug),
        ]);
    }

    public function update(Request $request, Role $role, RolePermissionService $service)
    {
        $this->authorizeSuperadmin();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $validKeys = array_keys($service->definitionsForRole($role));
        $enabled = array_values(array_intersect($data['permissions'] ?? [], $validKeys));

        $service->updateRole($role, $data, $enabled);

        return redirect()
            ->route('admin.roles.edit', $role->slug)
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role, RolePermissionService $service)
    {
        $this->authorizeSuperadmin();

        try {
            $service->deleteRole($role);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.roles.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }

    private function portalPermissionOptions(): array
    {
        $service = app(RolePermissionService::class);
        $options = [];

        foreach (config('permissions.portals', []) as $portal => $meta) {
            $options[$portal] = $service->portalDefinitions($portal);
        }

        return $options;
    }

    private function authorizeSuperadmin(): void
    {
        $admin = Auth::guard('web_admin')->user();
        if (!$admin || !$admin->isSuperadmin()) {
            abort(403, 'Only superadmin can manage roles.');
        }
    }
}
