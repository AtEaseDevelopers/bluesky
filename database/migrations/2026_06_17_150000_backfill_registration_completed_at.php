<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('registration_completed_at')
            ->whereNotNull('email')
            ->where('name', 'not like', 'Invite-%')
            ->update(['registration_completed_at' => now()]);
    }

    public function down(): void
    {
        // No rollback — existing accounts should remain marked as registered.
    }
};
