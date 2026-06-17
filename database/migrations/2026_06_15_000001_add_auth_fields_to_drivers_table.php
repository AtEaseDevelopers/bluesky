<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAuthFieldsToDriversTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('drivers', 'phone')) {
                $table->string('phone')->nullable()->after('name');
            }
            if (!Schema::hasColumn('drivers', 'username')) {
                $table->string('username')->nullable()->unique()->after('phone');
            }
            if (!Schema::hasColumn('drivers', 'password')) {
                $table->string('password')->nullable()->after('username');
            }
            if (!Schema::hasColumn('drivers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('lorry_number');
            }
            if (!Schema::hasColumn('drivers', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['name', 'phone', 'username', 'password', 'is_active', 'remember_token']);
        });
    }
}
