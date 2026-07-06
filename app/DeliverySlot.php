<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DeliverySlot extends Model
{
    protected $fillable = [
        'time_start',
        'time_end',
        'max_orders',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function getTimeLabelAttribute(): string
    {
        return date('H:i', strtotime($this->time_start)) . ' - ' . date('H:i', strtotime($this->time_end));
    }

    public function bookedCountForDate(string $date): int
    {
        return Order::query()
            ->where('delivery_slot_id', $this->id)
            ->whereDate('delivery_date', $date)
            ->where('status', '!=', Order::$status['cancelled'])
            ->count();
    }

    public function isAvailableForDate(string $date): bool
    {
        if (!$this->is_enabled || DeliveryBlackout::isDateBlocked($date)) {
            return false;
        }

        if ($this->max_orders === null) {
            return true;
        }

        return $this->bookedCountForDate($date) < $this->max_orders;
    }

    public static function enabledSlots(): Collection
    {
        return static::query()
            ->where('is_enabled', true)
            ->orderBy('time_start')
            ->get();
    }

    /** @return array<int, string> */
    public static function availableDates(int $daysAhead = 30): array
    {
        $dates = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = now()->addDays($i)->toDateString();
            if (!DeliveryBlackout::isDateBlocked($date)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    public static function slotsAvailableForDate(string $date): Collection
    {
        if (DeliveryBlackout::isDateBlocked($date)) {
            return collect();
        }

        return static::enabledSlots()
            ->filter(fn (self $slot) => $slot->isAvailableForDate($date))
            ->values();
    }
}
