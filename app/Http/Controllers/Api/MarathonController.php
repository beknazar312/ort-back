<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarathonSession;
use App\Models\MarathonSessionAnswer;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarathonController extends Controller
{
    use AuthorizesRequests;
    public function start(string $slug): JsonResponse
    {
        $subject = Subject::where('slug', $slug)->firstOrFail();
        $user = auth()->user();

        $session = MarathonSession::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'score' => 0,
            'lives_used' => 0,
            'questions_answered' => 0,
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'subject' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'slug' => $subject->slug,
                    'question_time_seconds' => $subject->question_time_seconds ?? 30,
                ],
                'initial_lives' => 3,
            ],
        ]);
    }

    public function getQuestion(MarathonSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $answeredQuestionIds = $session->answers()->pluck('question_id');

        $question = Question::where('subject_id', $session->subject_id)
            ->active()
            ->whereNotIn('id', $answeredQuestionIds)
            ->with(['answers' => function ($query) {
                $query->select('id', 'question_id', 'text', 'sort_order');
            }])
            ->inRandomOrder()
            ->first();

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'No more questions available',
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $question->id,
                'text' => $question->text,
                'answers' => $question->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ]),
            ],
        ]);
    }

    public function submitAnswer(Request $request, MarathonSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id' => 'required|exists:answers,id',
            'time_remaining' => 'required|numeric|min:0',
        ]);

        $question = Question::with('answers')->findOrFail($validated['question_id']);
        $correctAnswer = $question->correctAnswer();
        $isCorrect = $correctAnswer && $correctAnswer->id === (int)$validated['answer_id'];

        MarathonSessionAnswer::create([
            'marathon_session_id' => $session->id,
            'question_id' => $validated['question_id'],
            'answer_id' => $validated['answer_id'],
            'is_correct' => $isCorrect,
            'time_remaining' => $validated['time_remaining'],
        ]);

        if ($isCorrect) {
            $session->increment('score');
        } else {
            $session->increment('lives_used');
        }
        $session->increment('questions_answered');

        $freshSession = $session->fresh();

        return response()->json([
            'success' => true,
            'data' => [
                'is_correct' => $isCorrect,
                'correct_answer' => [
                    'id' => $correctAnswer->id,
                    'text' => $correctAnswer->text,
                ],
                'correct_answers' => $freshSession->score,
                'lives_remaining' => 3 - $freshSession->lives_used,
            ],
        ]);
    }

    public function complete(Request $request, MarathonSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $session->update([
            'completed_at' => now(),
        ]);

        $user = auth()->user();
        $previousBest = MarathonSession::forUser($user->id)
            ->forSubject($session->subject_id)
            ->completed()
            ->where('id', '!=', $session->id)
            ->max('score');

        $isPersonalBest = $previousBest === null || $session->score > $previousBest;

        if ($isPersonalBest) {
            $session->update(['is_personal_best' => true]);
        }

        $answersWithDetails = $session->answers()
            ->with(['question.answers', 'answer'])
            ->get()
            ->map(function ($sessionAnswer) {
                $question = $sessionAnswer->question;
                $correctAnswer = $question->correctAnswer();

                return [
                    'question' => [
                        'id' => $question->id,
                        'text' => $question->text,
                        'explanation' => $question->explanation,
                    ],
                    'selected_answer' => [
                        'id' => $sessionAnswer->answer->id,
                        'text' => $sessionAnswer->answer->text,
                    ],
                    'correct_answer' => [
                        'id' => $correctAnswer->id,
                        'text' => $correctAnswer->text,
                    ],
                    'is_correct' => $sessionAnswer->is_correct,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'correct_answers' => $session->score,
                'questions_answered' => $session->questions_answered,
                'lives_used' => $session->lives_used,
                'is_personal_best' => $isPersonalBest,
                'personal_best' => $isPersonalBest ? $session->score : $previousBest,
                'answers' => $answersWithDetails,
            ],
        ]);
    }

    public function getPersonalBest(string $slug): JsonResponse
    {
        $subject = Subject::where('slug', $slug)->firstOrFail();
        $user = auth()->user();

        $bestSession = MarathonSession::forUser($user->id)
            ->forSubject($subject->id)
            ->completed()
            ->orderByDesc('score')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'personal_best' => $bestSession?->score ?? 0,
                'total_games' => MarathonSession::forUser($user->id)
                    ->forSubject($subject->id)
                    ->completed()
                    ->count(),
            ],
        ]);
    }
}
