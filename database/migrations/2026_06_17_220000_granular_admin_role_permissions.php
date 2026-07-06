<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        $service = app(\App\Services\RolePermissionService::class);
        $now = now();
        $legacyRows = DB::table('role_permissions')
            ->where('permission', 'not like', '%.%')
            ->get();

        foreach ($legacyRows as $row) {
            $role = $service->findRole($row->role);
            if (! $role) {
                continue;
            }

            $module = $row->permission;
            $definition = $service->portalDefinitions($role->portal)[$module] ?? null;
            if (! $definition) {
                continue;
            }

            foreach ($service->capabilitiesForModule($definition) as $capability) {
                $key = $service->flatPermissionKey($module, $capability);

                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $row->role, 'permission' => $key],
                    [
                        'allowed' => (bool) $row->allowed,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            DB::table('role_permissions')
                ->where('role', $row->role)
                ->where('permission', $module)
                ->delete();
        }
    }

    public function down(): void
    {
        // Non-destructive: granular keys remain in place.
    }
};
