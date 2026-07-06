<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_blackouts', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('label')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date', 'is_enabled']);
        });

        if (Schema::hasColumn('delivery_slots', 'slot_date')) {
            DB::table('orders')
                ->join('delivery_slots', 'orders.delivery_slot_id', '=', 'delivery_slots.id')
                ->whereNull('orders.delivery_date')
                ->update(['orders.delivery_date' => DB::raw('delivery_slots.slot_date')]);

            $this->consolidateDeliverySlots();

            Schema::table('delivery_slots', function (Blueprint $table) {
                $table->dropColumn('slot_date');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('delivery_slots', 'slot_date')) {
            Schema::table('delivery_slots', function (Blueprint $table) {
                $table->date('slot_date')->nullable()->after('id');
                $table->index(['slot_date', 'is_enabled']);
            });

            DB::table('delivery_slots')->update([
                'slot_date' => now()->addDay()->toDateString(),
            ]);
        }

        Schema::dropIfExists('delivery_blackouts');
    }

    private function consolidateDeliverySlots(): void
    {
        $groups = DB::table('delivery_slots')
            ->select('time_start', 'time_end')
            ->groupBy('time_start', 'time_end')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('delivery_slots')
                ->where('time_start', $group->time_start)
                ->where('time_end', $group->time_end)
                ->orderBy('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $canonical = $rows->first();
            $duplicateIds = $rows->slice(1)->pluck('id')->all();

            DB::table('orders')
                ->whereIn('delivery_slot_id', $duplicateIds)
                ->update(['delivery_slot_id' => $canonical->id]);

            DB::table('delivery_slots')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }
    }
};
