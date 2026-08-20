<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Helper extends Model
{
    public static function member_url($route="") {
        return config('app.url')."/".$route;
    }

    public static function admin_url($route="") {
        return config('app.admin_url')."/".$route;
    }

    public static function query_params($query=[]) {
        return "?".http_build_query($query);
    }

    public static function likePattern(?string $term): ?string
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }

        return '%' . addcslashes($term, '%_\\') . '%';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $columns
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function applyOrLikeSearch($query, array $columns, ?string $term)
    {
        $pattern = self::likePattern($term);
        if ($pattern === null || $columns === []) {
            return $query;
        }

        return $query->where(function ($inner) use ($columns, $pattern) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $inner->where($column, 'LIKE', $pattern);
                    continue;
                }

                $inner->orWhere($column, 'LIKE', $pattern);
            }
        });
    }

    public static function generateRandomString($length = 30, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $randomString = '';
    
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
    
        return $randomString;
    }
}