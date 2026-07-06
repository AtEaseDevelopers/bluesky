<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeliveryBlackout extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'label',
        'is_enabled',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_enabled' => 'boolean',
    ];

    public function coversDate(string $date): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        return $date >= $this->start_date->toDateString()
            && $date <= $this->end_date->toDateString();
    }

    public function dateRangeLabel(): string
    {
        $start = $this->start_date->format('d M Y');
        $end = $this->end_date->format('d M Y');

        if ($start === $end) {
            return $start;
        }

        return $start . ' – ' . $end;
    }

    public static function isDateBlocked(string $date): bool
    {
        return static::query()
            ->where('is_enabled', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }
}
