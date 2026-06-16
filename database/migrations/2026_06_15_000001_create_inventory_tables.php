<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('weight', 15, 3)->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('movement_type', 30);
            $table->decimal('quantity_before', 15, 3)->default(0);
            $table->decimal('quantity_change', 15, 3);
            $table->decimal('quantity_after', 15, 3)->default(0);
            $table->decimal('weight', 15, 3)->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->date('movement_date');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('admin_id')->references('id')->on('admins');
            $table->index(['product_id', 'movement_type']);
            $table->index('movement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
    }
};
