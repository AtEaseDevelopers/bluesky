<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_method', 20)->nullable()->after('attn_contact');
            $table->string('wechat_id', 100)->nullable()->after('contact_method');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('contact_method', 20)->nullable()->after('attn_contact');
            $table->string('wechat_id', 100)->nullable()->after('contact_method');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_method', 'wechat_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['contact_method', 'wechat_id']);
        });
    }
};
