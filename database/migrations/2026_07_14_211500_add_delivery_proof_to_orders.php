<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_proof')->nullable()->after('pickup_confirmed_by');
            $table->timestamp('delivery_confirmed_at')->nullable()->after('delivery_proof');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_proof', 'delivery_confirmed_at']);
        });
    }
};
