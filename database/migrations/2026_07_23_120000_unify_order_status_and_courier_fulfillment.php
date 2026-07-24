<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnifyOrderStatusAndCourierFulfillment extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->where('status', 'handed_to_customer')
                ->update(['status' => 'delivered']);

            if (!Schema::hasColumn('orders', 'courier_proof')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->string('courier_proof')->nullable()->after('pickup_confirmed_by');
                    $table->timestamp('courier_confirmed_at')->nullable()->after('courier_proof');
                    $table->unsignedBigInteger('courier_confirmed_by')->nullable()->after('courier_confirmed_at');
                });
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'courier_proof')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['courier_proof', 'courier_confirmed_at', 'courier_confirmed_by']);
            });
        }
    }
}
