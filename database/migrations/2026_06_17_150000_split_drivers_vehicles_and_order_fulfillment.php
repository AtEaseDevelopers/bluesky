<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 50)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasColumn('drivers', 'lorry_number')) {
            $existingNumbers = \Illuminate\Support\Facades\DB::table('drivers')
                ->whereNotNull('lorry_number')
                ->where('lorry_number', '!=', '')
                ->distinct()
                ->pluck('lorry_number');

            foreach ($existingNumbers as $number) {
                \Illuminate\Support\Facades\DB::table('vehicles')->insertOrIgnore([
                    'vehicle_number' => $number,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn('lorry_number');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type', 20)->default('delivery')->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('lorry_number')->nullable()->after('password');
        });

        Schema::dropIfExists('vehicles');
    }
};
