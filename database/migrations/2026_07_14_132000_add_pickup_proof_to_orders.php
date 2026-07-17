<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_proof')->nullable()->after('transfer_slip');
            $table->timestamp('pickup_confirmed_at')->nullable()->after('pickup_proof');
            $table->unsignedBigInteger('pickup_confirmed_by')->nullable()->after('pickup_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_proof', 'pickup_confirmed_at', 'pickup_confirmed_by']);
        });
    }
};
