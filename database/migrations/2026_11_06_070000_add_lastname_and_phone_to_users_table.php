<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the full name into `name` (first name) and `lastname`, and adds an
 * optional `phone`. Existing users are split at the first space.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lastname')->default('')->after('name');
            $table->string('phone', 30)->nullable()->after('email');
        });

        DB::table('users')->orderBy('id')->each(function (object $user) {
            [$name, $lastname] = array_pad(preg_split('/\s+/u', trim($user->name), 2) ?: [], 2, '');

            DB::table('users')->where('id', $user->id)->update(['name' => $name, 'lastname' => $lastname]);
        });
    }

    public function down(): void
    {
        DB::table('users')->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['name' => trim("{$user->name} {$user->lastname}")]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lastname', 'phone']);
        });
    }
};
