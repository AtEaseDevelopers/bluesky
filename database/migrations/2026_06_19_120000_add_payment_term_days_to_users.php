<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'payment_term_days')) {
                $table->unsignedSmallInteger('payment_term_days')->nullable()->after('credit_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'payment_term_days')) {
                $table->dropColumn('payment_term_days');
            }
        });
    }
};
