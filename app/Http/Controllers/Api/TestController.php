<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestAttemptAnswer;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(): JsonResponse
    {
        $tests = Test::active()
            ->with('subject')
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tests->map(fn ($test) => [
                'id' => $test->id,
                'name' => $test->name,
                'description' => $test->description,
                'subject' => $test->subject ? [
                    'id' => $test->subject->id,
                    'name' => $test->subject->name,
                    'slug' => $test->subject->slug,
                ] : null,
                'time_limit_minutes' => $test->time_limit_minutes,
                'question_count' => $test->question_count,
                'questions_count' => $test->questions_count,
            ]),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $test = Test::active()
            ->with('subject')
            ->withCount('questions')
            ->find($id);

        if (!$test) {
            return response()->json([
                'success' => false,
                'message' => 'Тест не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $test->id,
                'name' => $test->name,
                'description' => $test->description,
                'subject' => $test->subject ? [
                    'id' => $test->subject->id,
                    'name' => $test->subject->name,
                    'slug' => $test->subject->slug,
                ] : null,
                'time_limit_minutes' => $test->time_limit_minutes,
                'question_count' => $test->question_count,
                'questions_count' => $test->questions_count,
            ],
        ]);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $test = Test::active()->with('questions.answers')->find($id);

        if (!$test) {
            return response()->json([
                'success' => false,
                'message' => 'Тест не найден',
            ], 404);
        }

        $user = $request->user();

        // Check if user has an incomplete attempt for this test
        $existingAttempt = TestAttempt::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->whereNull('completed_at')
            ->first();

        if ($existingAttempt) {
            // Return existing attempt data instead of error
            return $this->getAttemptData($existingAttempt);
        }

        // Get questions for the test
        $questions = $test->questions()->active()->get();

        // If not enough questions attached, get random questions from subject
        if ($questions->count() < $test->question_count && $test->subject_id) {
            $additionalQuestions = Question::active()
                ->where('subject_id', $test->subject_id)
                ->whereNotIn('id', $questions->pluck('id'))
                ->inRandomOrder()
                ->limit($test->question_count - $questions->count())
                ->get();

            $questions = $questions->merge($additionalQuestions);
        }

        // Shuffle questions
        $questions = $questions->shuffle()->take($test->question_count);

        // Create attempt
        $attempt = TestAttempt::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'subject_id' => $test->subject_id,
            'mode' => 'test',
            'total_questions' => $questions->count(),
            'correct_answers' => 0,
            'score' => 0,
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'test' => [
                    'id' => $test->id,
                    'name' => $test->name,
                    'time_limit_minutes' => $test->time_limit_minutes,
                ],
                'questions' => $questions->map(fn ($q, $index) => [
                    'id' => $q->id,
                    'index' => $index + 1,
                    'text' => $q->text,
                    'difficulty' => $q->difficulty,
                    'answers' => $q->answers->map(fn ($a) => [
                        'id' => $a->id,
                        'text' => $a->text,
                    ]),
                ]),
                'started_at' => $attempt->started_at->toISOString(),
                'expires_at' => $attempt->started_at->addMinutes($test->time_limit_minutes)->toISOString(),
            ],
        ]);
    }

    public function submit(Request $request, int $attemptId): JsonResponse
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.answer_id' => 'nullable|integer|exists:answers,id',
        ]);

        $user = $request->user();

        $attempt = TestAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->first();

        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Попытка не найдена или уже завершена',
            ], 404);
        }

        $correctCount = 0;
        $answers = $request->answers;

        foreach ($answers as $answerData) {
            $question = Question::with('answers')->find($answerData['question_id']);
            if (!$question) continue;

            $selectedAnswer = $answerData['answer_id']
                ? $question->answers->firstWhere('id', $answerData['answer_id'])
                : null;

            $isCorrect = $selectedAnswer && $selectedAnswer->is_correct;
            if ($isCorrect) $correctCount++;

            TestAttemptAnswer::updateOrCreate(
                [
                    'test_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ],
                [
                    'answer_id' => $selectedAnswer?->id,
                    'is_correct' => $isCorrect,
                ]
            );
        }

        $attempt->correct_answers = $correctCount;
        $attempt->calculateScore();
        $attempt->completed_at = now();
        $attempt->time_spent_seconds = $attempt->started_at->diffInSeconds($attempt->completed_at);
        $attempt->save();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'total_questions' => $attempt->total_questions,
                'correct_answers' => $attempt->correct_answers,
                'score' => $attempt->score,
                'time_spent_seconds' => $attempt->time_spent_seconds,
            ],
        ]);
    }

    public function results(int $attemptId): JsonResponse
    {
        $attempt = TestAttempt::with([
            'test',
            'subject',
            'answers.question.answers',
            'answers.answer',
        ])->find($attemptId);

        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Попытка не найдена',
            ], 404);
        }

        if (!$attempt->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Тест еще не завершен',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'test' => $attempt->test ? [
                    'id' => $attempt->test->id,
                    'name' => $attempt->test->name,
                ] : null,
                'subject' => $attempt->subject ? [
                    'id' => $attempt->subject->id,
                    'name' => $attempt->subject->name,
                ] : null,
                'mode' => $attempt->mode,
                'total_questions' => $attempt->total_questions,
                'correct_answers' => $attempt->correct_answers,
                'score' => $attempt->score,
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'started_at' => $attempt->started_at?->toISOString(),
                'completed_at' => $attempt->completed_at?->toISOString(),
                'questions' => $attempt->answers->map(fn ($attemptAnswer) => [
                    'id' => $attemptAnswer->question->id,
                    'text' => $attemptAnswer->question->text,
                    'explanation' => $attemptAnswer->question->explanation,
                    'is_correct' => $attemptAnswer->is_correct,
                    'selected_answer_id' => $attemptAnswer->answer_id,
                    'answers' => $attemptAnswer->question->answers->map(fn ($a) => [
                        'id' => $a->id,
                        'text' => $a->text,
                        'is_correct' => $a->is_correct,
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * Resume an existing unfinished test attempt
     */
    public function resume(Request $request, int $attemptId): JsonResponse
    {
        $user = $request->user();

        $attempt = TestAttempt::with(['test.questions.answers'])
            ->where('id', $attemptId)
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->first();

        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Незавершенная попытка не найдена',
            ], 404);
        }

        return $this->getAttemptData($attempt);
    }

    /**
     * Get formatted attempt data for response
     */
    private function getAttemptData(TestAttempt $attempt): JsonResponse
    {
        $attempt->load(['test.questions.answers', 'answers']);

        $test = $attempt->test;

        // Get questions that were in this attempt (from answers if any, or test questions)
        if ($attempt->answers->isNotEmpty()) {
            $questionIds = $attempt->answers->pluck('question_id');
            $questions = Question::with('answers')
                ->whereIn('id', $questionIds)
                ->get();
        } else {
            // If no answers saved yet, get questions from the test
            $questions = $test->questions()->active()->get();

            // If not enough, get random from subject
            if ($questions->count() < $test->question_count && $test->subject_id) {
                $additionalQuestions = Question::active()
                    ->where('subject_id', $test->subject_id)
                    ->whereNotIn('id', $questions->pluck('id'))
                    ->inRandomOrder()
                    ->limit($test->question_count - $questions->count())
                    ->get();
                $questions = $questions->merge($additionalQuestions);
            }

            $questions = $questions->take($test->question_count);
        }

        // Get previously saved answers
        $savedAnswers = $attempt->answers->pluck('answer_id', 'question_id')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'test' => [
                    'id' => $test->id,
                    'name' => $test->name,
                    'time_limit_minutes' => $test->time_limit_minutes,
                ],
                'questions' => $questions->map(fn ($q, $index) => [
                    'id' => $q->id,
                    'index' => $index + 1,
                    'text' => $q->text,
                    'difficulty' => $q->difficulty,
                    'answers' => $q->answers->map(fn ($a) => [
                        'id' => $a->id,
                        'text' => $a->text,
                    ]),
                ]),
                'saved_answers' => $savedAnswers,
                'started_at' => $attempt->started_at->toISOString(),
                'expires_at' => $attempt->started_at->addMinutes($test->time_limit_minutes)->toISOString(),
            ],
        ]);
    }
}
