<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TestAttempt;
use App\Models\TestAttemptAnswer;
use App\Models\Answer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function random(Request $request, string $slug): JsonResponse
    {
        $subject = Subject::active()->where('slug', $slug)->first();

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Предмет не найден',
            ], 404);
        }

        $question = Question::active()
            ->where('subject_id', $subject->id)
            ->with(['answers' => fn ($q) => $q->orderBy('sort_order')])
            ->inRandomOrder()
            ->first();

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Вопросы не найдены',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $question->id,
                'text' => $question->text,
                'difficulty' => $question->difficulty,
                'subject' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'slug' => $subject->slug,
                ],
                'answers' => $question->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                ]),
            ],
        ]);
    }

    public function answer(Request $request, int $questionId): JsonResponse
    {
        $request->validate([
            'answer_id' => 'required|integer|exists:answers,id',
        ]);

        $question = Question::with(['answers', 'subject'])->find($questionId);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Вопрос не найден',
            ], 404);
        }

        $selectedAnswer = Answer::find($request->answer_id);

        if (!$selectedAnswer || $selectedAnswer->question_id !== $question->id) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный ответ',
            ], 400);
        }

        $isCorrect = $selectedAnswer->is_correct;
        $correctAnswer = $question->answers->firstWhere('is_correct', true);

        // Save practice attempt
        $user = $request->user();

        $attempt = TestAttempt::create([
            'user_id' => $user->id,
            'subject_id' => $question->subject_id,
            'mode' => 'practice',
            'total_questions' => 1,
            'correct_answers' => $isCorrect ? 1 : 0,
            'score' => $isCorrect ? 100 : 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        TestAttemptAnswer::create([
            'test_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer_id' => $selectedAnswer->id,
            'is_correct' => $isCorrect,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'is_correct' => $isCorrect,
                'correct_answer' => [
                    'id' => $correctAnswer->id,
                    'text' => $correctAnswer->text,
                ],
                'explanation' => $question->explanation,
            ],
        ]);
    }
}
