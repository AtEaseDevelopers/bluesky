<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('category')->nullable()->after('name');
            $table->string('shipping_address')->after('category');
            $table->string('shipping_postcode', 10)->after('shipping_address');
            $table->string('shipping_state', 30)->after('shipping_postcode');
            $table->text('payment_method')->after('shipping_state');
            $table->text('login_code')->after('payment_method')->comment('when user login with link must match this code');
            $table->text('remark')->nullable()->after('login_code')->comment('remark for admin to refer');
            $table->string('status', 30)->after('remark');
            $table->boolean('price_permission')->default(1);
            $table->boolean('invoice_visibility')->default(1);
            $table->boolean('invoice_price_permission')->default(1);
            $table->integer('default_driver_id')->nullable();
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
            $table->dropColumn('category');
            $table->dropColumn('shipping_address');
            $table->dropColumn('shipping_postcode');
            $table->dropColumn('shipping_state');
            $table->dropColumn('payment_method');
            $table->dropColumn('login_code');
            $table->dropColumn('remark');
            $table->dropColumn('status');
        });
    }
}
