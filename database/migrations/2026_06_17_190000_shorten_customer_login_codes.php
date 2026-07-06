<?php

use App\User;
use Illuminate\Database\Migrations\Migration;

class ShortenCustomerLoginCodes extends Migration
{
    public function up()
    {
        User::query()
            ->whereNotNull('login_code')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) {
                $code = (string) $user->login_code;
                if (strlen($code) > User::LOGIN_CODE_LENGTH || !preg_match('/^[A-Za-z0-9]+$/', $code)) {
                    $user->update(['login_code' => User::generateLoginCode()]);
                }
            });
    }

    public function down()
    {
        //
    }
}
