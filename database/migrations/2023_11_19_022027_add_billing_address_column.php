<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillingAddressColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_address')->after('category');
            $table->string('billing_postcode', 10)->after('billing_address');
            $table->string('billing_state', 30)->after('billing_postcode');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_address')->after('total_price');
            $table->string('billing_postcode', 10)->after('billing_address');
            $table->string('billing_state', 30)->after('billing_postcode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('billing_address');
            $table->dropColumn('billing_postcode');
            $table->dropColumn('billing_state');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('billing_address');
            $table->dropColumn('billing_postcode');
            $table->dropColumn('billing_state');
        });
    }
}
