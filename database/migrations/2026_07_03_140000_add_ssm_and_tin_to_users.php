<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ssm')) {
                $table->string('ssm', 50)->nullable()->after('sql_customer_code');
            }
            if (!Schema::hasColumn('users', 'tin_no')) {
                $table->string('tin_no', 50)->nullable()->after('ssm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['tin_no', 'ssm'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
