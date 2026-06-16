<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('autocount_sync_status', 30)->default('pending')->after('invoice_number');
            $table->timestamp('autocount_synced_at')->nullable()->after('autocount_sync_status');
        });

        Schema::create('autocount_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('invoice_number')->nullable();
            $table->string('sync_status', 30);
            $table->text('response_message')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autocount_sync_logs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['autocount_sync_status', 'autocount_synced_at']);
        });
    }
};
