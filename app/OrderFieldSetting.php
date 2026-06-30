<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderFieldSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function isSituationOption(string $optionName): bool
    {
        return str_contains(strtolower(trim($optionName)), 'situation');
    }
}
