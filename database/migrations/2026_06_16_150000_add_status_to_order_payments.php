<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('status', 20)->default('confirmed')->after('amount');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('recorded_by_driver');

            $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropColumn(['status', 'submitted_by_user_id']);
        });
    }
};
