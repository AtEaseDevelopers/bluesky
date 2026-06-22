<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_drivers')) {
            Schema::create('customer_drivers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('driver_id');
                $table->timestamps();

                $table->unique(['user_id', 'driver_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('users', 'default_driver_id')) {
            DB::table('users')
                ->whereNotNull('default_driver_id')
                ->orderBy('id')
                ->chunkById(100, function ($users) {
                    foreach ($users as $user) {
                        DB::table('customer_drivers')->insertOrIgnore([
                            'user_id' => $user->id,
                            'driver_id' => $user->default_driver_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_drivers');
    }
};
