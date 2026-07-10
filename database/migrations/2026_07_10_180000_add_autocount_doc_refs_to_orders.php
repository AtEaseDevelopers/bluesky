<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutocountDocRefsToOrders extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'api_do_id')) {
                $table->string('api_do_id', 50)->nullable()->after('autocount_synced_at');
            }
            if (!Schema::hasColumn('orders', 'api_invoice_id')) {
                $table->string('api_invoice_id', 50)->nullable()->after('api_do_id');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['api_do_id', 'api_invoice_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
