<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('permission', 60);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });

        $now = now();
        foreach (config('permissions.roles') as $role => $roleConfig) {
            foreach ($roleConfig['permissions'] as $permission => $definition) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'permission' => $permission,
                    'allowed' => $definition['default'] ?? true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
