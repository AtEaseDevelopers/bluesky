<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('weight_before', 15, 3)->nullable()->after('weight');
            $table->decimal('weight_change', 15, 3)->nullable()->after('weight_before');
            $table->decimal('weight_after', 15, 3)->nullable()->after('weight_change');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['weight_before', 'weight_change', 'weight_after']);
        });
    }
};
