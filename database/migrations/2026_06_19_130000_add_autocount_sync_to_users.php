<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'autocount_sync_status')) {
                $table->string('autocount_sync_status', 30)->default('pending')->after('sql_customer_code');
            }
            if (!Schema::hasColumn('users', 'autocount_synced_at')) {
                $table->timestamp('autocount_synced_at')->nullable()->after('autocount_sync_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['autocount_sync_status', 'autocount_synced_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
