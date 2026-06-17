<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MigratePaidCompletedToDelivered extends Migration
{
    public function up()
    {
        DB::table('orders')
            ->where('status', 'paid_completed')
            ->update(['status' => 'delivered']);
    }

    public function down()
    {
        DB::table('orders')
            ->where('status', 'delivered')
            ->whereNotNull('completed_at')
            ->where('payment_status', 'paid')
            ->update(['status' => 'paid_completed']);
    }
}
