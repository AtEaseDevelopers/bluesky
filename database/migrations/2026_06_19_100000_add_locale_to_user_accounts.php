<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocaleToUserAccounts extends Migration
{
    public function up()
    {
        foreach (['users', 'admins', 'drivers'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'locale')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('locale', 10)->nullable();
                });
            }
        }
    }

    public function down()
    {
        foreach (['users', 'admins', 'drivers'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'locale')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('locale');
                });
            }
        }
    }
}
