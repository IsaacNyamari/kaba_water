<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clients without an email address sign in with phone number + meter
     * number + PIN, so a user's email becomes optional. It stays unique
     * (multiple NULLs are allowed by unique indexes).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Give email-less users a placeholder so the column can be required again.
        DB::table('users')->whereNull('email')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['email' => "user{$user->id}@no-email.invalid"]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
