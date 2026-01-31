<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marathon_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marathon_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('answer_id')->constrained()->onDelete('cascade');
            $table->boolean('is_correct');
            $table->decimal('time_remaining', 8, 2);
            $table->timestamps();

            $table->index('marathon_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marathon_session_answers');
    }
};
