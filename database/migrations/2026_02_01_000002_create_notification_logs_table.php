<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'streak_reminder', 'milestone'
            $table->string('scenario')->nullable(); // 'evening', 'last_chance'
            $table->integer('streak_count')->nullable();
            $table->boolean('sent_successfully')->default(false);
            $table->text('error_message')->nullable();
            $table->date('notification_date');
            $table->timestamps();
            $table->index(['user_id', 'notification_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
