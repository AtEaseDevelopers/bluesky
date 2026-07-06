<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('role', 30);
                $table->string('permission', 60);
                $table->boolean('allowed')->default(true);
                $table->timestamps();

                $table->unique(['role', 'permission']);
            });
        }

        if (DB::table('role_permissions')->count() > 0) {
            return;
        }

        $now = now();
        $portals = config('permissions.portals', []);

        foreach ($portals as $role => $roleConfig) {
            foreach ($roleConfig['permissions'] ?? [] as $permission => $definition) {
                $defaults = $definition['default'] ?? true;
                $rows = [];

                if (is_array($defaults) && isset($definition['capabilities'])) {
                    foreach ($definition['capabilities'] as $capability => $capDefinition) {
                        $rows[] = [
                            'role' => $role,
                            'permission' => $permission . '.' . $capability,
                            'allowed' => (bool) ($defaults[$capability] ?? false),
                        ];
                    }
                } else {
                    $rows[] = [
                        'role' => $role,
                        'permission' => $permission,
                        'allowed' => (bool) $defaults,
                    ];
                }

                foreach ($rows as $row) {
                    DB::table('role_permissions')->insert(array_merge($row, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
