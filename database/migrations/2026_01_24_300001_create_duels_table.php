<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_code')->unique();
            $table->foreignId('challenger_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('opponent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');

            $table->integer('initial_lives')->default(3);
            $table->integer('time_per_question')->default(30); // seconds
            $table->integer('challenger_lives')->default(3);
            $table->integer('opponent_lives')->default(3);
            $table->integer('current_question_index')->default(0);

            $table->enum('status', ['pending', 'active', 'completed', 'declined', 'surrendered', 'expired'])->default('pending');
            $table->foreignId('surrendered_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['challenger_id', 'status']);
            $table->index(['opponent_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duels');
    }
};
