<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duel_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duel_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->integer('question_order');

            $table->foreignId('challenger_answer_id')->nullable()->constrained('answers')->onDelete('set null');
            $table->foreignId('opponent_answer_id')->nullable()->constrained('answers')->onDelete('set null');

            $table->boolean('challenger_is_correct')->nullable();
            $table->boolean('opponent_is_correct')->nullable();

            $table->timestamp('challenger_answered_at')->nullable();
            $table->timestamp('opponent_answered_at')->nullable();

            $table->enum('round_result', ['challenger_wins', 'opponent_wins', 'draw', 'pending'])->default('pending');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['duel_id', 'question_order']);
            $table->index(['duel_id', 'round_result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_questions');
    }
};
