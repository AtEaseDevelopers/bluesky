<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OrderFieldSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        return Cache::remember('order_field_setting.' . $key, 300, function () use ($key, $default) {
            $row = static::where('key', $key)->first();

            return $row ? $row->value : $default;
        });
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('order_field_setting.' . $key);
    }

    public static function weightPresets(): array
    {
        $raw = json_decode(static::getValue('weight_presets', '["1","1.5","2"]'), true);

        return is_array($raw) ? array_values(array_filter($raw)) : ['1', '1.5', '2'];
    }

    public static function situationOptions(): array
    {
        $raw = json_decode(static::getValue('situation_options', '["live","kill","clean"]'), true);

        return is_array($raw) ? array_values(array_filter($raw)) : ['live', 'kill', 'clean'];
    }

    public static function situationLabel(): string
    {
        return static::getValue('situation_label', 'Situation') ?: 'Situation';
    }

    public static function isSituationOption(string $optionName): bool
    {
        return strcasecmp(trim($optionName), static::situationLabel()) === 0;
    }
}
