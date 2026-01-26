<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->unique()->nullable()->after('id');
            $table->string('username')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('username');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('photo_url')->nullable()->after('last_name');
            $table->string('language_code', 10)->nullable()->after('photo_url');
            $table->boolean('is_premium')->default(false)->after('language_code');
            $table->timestamp('telegram_auth_date')->nullable()->after('is_premium');

            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_id',
                'username',
                'first_name',
                'last_name',
                'photo_url',
                'language_code',
                'is_premium',
                'telegram_auth_date'
            ]);
        });
    }
};
