<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'area')) {
                $table->unsignedBigInteger('area')->nullable()->after('attn_contact');
            }
            if (!Schema::hasColumn('users', 'billing_city')) {
                $table->string('billing_city')->nullable()->after('billing_address');
            }
            if (!Schema::hasColumn('users', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('users', 'sql_customer_code')) {
                $table->string('sql_customer_code')->nullable()->after('default_driver_id');
            }
            if (!Schema::hasColumn('users', 'fax_no')) {
                $table->string('fax_no', 20)->nullable()->after('attn_contact');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['area', 'billing_city', 'shipping_city', 'sql_customer_code', 'fax_no'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
