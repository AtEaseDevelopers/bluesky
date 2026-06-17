<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeOrdersUserIdNullableAddGeneralFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // General Customer (public) orders are placed without an account.
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->boolean('is_general')->default(false)->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_general');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
}
