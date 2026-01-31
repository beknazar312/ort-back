<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marathon_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->integer('score')->default(0);
            $table->integer('lives_used')->default(0);
            $table->integer('questions_answered')->default(0);
            $table->boolean('is_personal_best')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'subject_id']);
            $table->index(['user_id', 'subject_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marathon_sessions');
    }
};
