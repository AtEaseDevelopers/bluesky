<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderFieldSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function deliveryOrderShowsPrices(): bool
    {
        return static::getValue('do_show_prices', '0') === '1';
    }

    public static function isSituationOption(string $optionName): bool
    {
        return str_contains(strtolower(trim($optionName)), 'situation');
    }
}
