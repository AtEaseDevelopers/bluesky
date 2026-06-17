<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicOrderLinksTable extends Migration
{
    public function up()
    {
        Schema::create('public_order_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('public_order_links');
    }
}
