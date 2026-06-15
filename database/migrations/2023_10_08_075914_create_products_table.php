<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('uom_id')->nullable();
            $table->integer('product_category_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('weight')->nullable();
            $table->longText('images')->nullable();
            $table->string('status', 15);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
