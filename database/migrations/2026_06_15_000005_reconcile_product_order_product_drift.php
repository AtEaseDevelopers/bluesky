<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile schema drift: these columns exist in the MySQL dev DB but were
 * never created by a migration, so the SQLite test DB lacked them. Each add
 * is guarded with hasColumn() so this is a no-op on the dev DB and additive
 * on any fresh/SQLite database.
 */
class ReconcileProductOrderProductDrift extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'remark')) {
                $table->text('remark')->nullable();
            }
            if (!Schema::hasColumn('products', 'nos')) {
                $table->integer('nos')->nullable();
            }
            if (!Schema::hasColumn('products', 'show_weight')) {
                $table->boolean('show_weight')->default(false);
            }
            if (!Schema::hasColumn('products', 'show_qty')) {
                $table->boolean('show_qty')->default(false);
            }
            if (!Schema::hasColumn('products', 'sell_in')) {
                $table->string('sell_in', 15)->nullable();
            }
        });

        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'nos')) {
                $table->integer('nos')->nullable();
            }
            if (!Schema::hasColumn('order_products', 'product_weight')) {
                $table->string('product_weight')->nullable();
            }
            // Weight-based lines carry no quantity.
            $table->integer('quantity')->nullable()->change();
        });

        Schema::table('cart_products', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_products', 'weight')) {
                $table->string('weight')->nullable();
            }
            // Weight-based lines carry no quantity.
            $table->integer('quantity')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            // Public / member orders may omit billing postcode & state.
            $table->string('billing_postcode', 5)->nullable()->change();
            $table->string('billing_state', 30)->nullable()->change();

            if (!Schema::hasColumn('orders', 'billing_city')) {
                $table->string('billing_city')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city')->nullable();
            }
            if (!Schema::hasColumn('orders', 'area')) {
                $table->string('area')->nullable();
            }
            if (!Schema::hasColumn('orders', 'do_no')) {
                $table->string('do_no')->nullable();
            }
            if (!Schema::hasColumn('orders', 'do_date')) {
                $table->date('do_date')->nullable();
            }
        });
    }

    public function down()
    {
        // No-op: these columns predate this migration in the dev DB.
    }
}
