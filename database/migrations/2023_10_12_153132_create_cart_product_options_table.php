<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartProductOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cart_product_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_product_id');
            $table->string('option');
            $table->string('option_item');
            $table->string('status', 15);
            $table->timestamps();

            $table->foreign('cart_product_id')->references('id')->on('cart_products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cart_product_options');
    }
}
