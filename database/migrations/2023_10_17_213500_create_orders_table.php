<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cart_id');
            $table->decimal('total_price', 15, 2);
            $table->text('shipping_address')->nullable();
            $table->string('shipping_postcode', 10)->nullable();
            $table->string('shipping_state', 30)->nullable();
            $table->text('payment_method');
            $table->text('transfer_slip')->nullable();
            $table->string('status', 15);
            $table->integer('driver_id')->nullable();
            $table->string('order_weight')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('cart_id')->references('id')->on('carts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
