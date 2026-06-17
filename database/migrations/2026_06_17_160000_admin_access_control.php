<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'status')) {
                $table->string('status', 20)->default('active')->after('role');
            }
        });

        DB::table('admins')->where('role', 'management')->update(['role' => 'admin']);
        DB::table('admins')->whereNull('status')->update(['status' => 'active']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'status')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        DB::table('admins')->where('role', 'admin')->update(['role' => 'management']);
    }
};
