<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 60)->unique();
            $table->string('portal', 20);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_superadmin')->default(false);
            $table->timestamps();
        });

        if (Schema::hasColumn('role_permissions', 'role')) {
            DB::statement('ALTER TABLE role_permissions MODIFY role VARCHAR(60) NOT NULL');
        }

        $now = now();
        $systemRoles = [
            [
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'portal' => 'admin',
                'description' => 'Full access to all admin modules and settings.',
                'is_system' => true,
                'is_superadmin' => true,
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'portal' => 'admin',
                'description' => 'Default back-office staff role.',
                'is_system' => true,
                'is_superadmin' => false,
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'portal' => 'customer',
                'description' => 'Default registered customer portal role.',
                'is_system' => true,
                'is_superadmin' => false,
            ],
            [
                'name' => 'Driver',
                'slug' => 'driver',
                'portal' => 'driver',
                'description' => 'Default delivery driver portal role.',
                'is_system' => true,
                'is_superadmin' => false,
            ],
        ];

        foreach ($systemRoles as $role) {
            DB::table('roles')->insert(array_merge($role, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->string('role', 30)->change();
        });
    }
};
