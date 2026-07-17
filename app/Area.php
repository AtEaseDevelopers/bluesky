<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'area_name'
    ];

    public static function optionsForSelect()
    {
        return static::query()->orderBy('area_name')->get(['id', 'area_name']);
    }

    public static function nameForId($id): ?string
    {
        if (!$id) {
            return null;
        }

        return static::where('id', $id)->value('area_name');
    }

    public static function orderStorageValue($input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_numeric($input)) {
            return static::nameForId((int) $input);
        }

        return (string) $input;
    }

    public static function orderFilterValue($input): ?string
    {
        return static::orderStorageValue($input);
    }

    public static function selectedIdForStored($stored): ?int
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (is_numeric($stored)) {
            return (int) $stored;
        }

        return static::where('area_name', $stored)->value('id');
    }
}
