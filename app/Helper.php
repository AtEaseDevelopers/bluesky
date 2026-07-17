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

    public static function generateRandomString($length = 30, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $randomString = '';
    
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
    
        return $randomString;
    }
}