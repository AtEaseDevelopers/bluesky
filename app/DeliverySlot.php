<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeliverySlot extends Model
{
    protected $fillable = [
        'slot_date',
        'time_start',
        'time_end',
        'max_orders',
        'is_enabled',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'is_enabled' => 'boolean',
    ];

    public function getTimeLabelAttribute(): string
    {
        return date('H:i', strtotime($this->time_start)) . ' - ' . date('H:i', strtotime($this->time_end));
    }

    public function isAvailable(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if ($this->max_orders === null) {
            return true;
        }

        $used = Order::where('delivery_slot_id', $this->id)
            ->where('status', '!=', Order::$status['cancelled'])
            ->count();

        return $used < $this->max_orders;
    }

    public static function availableSlots()
    {
        return static::where('is_enabled', true)
            ->where('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')
            ->orderBy('time_start')
            ->get()
            ->filter(fn ($slot) => $slot->isAvailable());
    }
}
