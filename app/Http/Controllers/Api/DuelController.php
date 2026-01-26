<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Duel;
use App\Models\DuelQuestion;
use App\Models\DuelStats;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Events\DuelInviteReceived;
use App\Events\DuelStarted;
use App\Events\DuelDeclined;
use App\Events\NewQuestion;
use App\Events\OpponentAnswered;
use App\Events\RoundResult;
use App\Events\DuelEnded;
use App\Jobs\ProcessDuelQuestionTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $duels = Duel::forUser($user)
            ->with(['challenger', 'opponent', 'subject', 'winner'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($duel) => $this->formatDuel($duel, $user));

        return response()->json([
            'success' => true,
            'data' => $duels,
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $duels = Duel::where('opponent_id', $user->id)
            ->pending()
            ->with(['challenger', 'subject'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($duel) => $this->formatDuel($duel, $user));

        return response()->json([
            'success' => true,
            'data' => $duels,
        ]);
    }

    public function show(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$duel->isParticipant($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $duel->load(['challenger', 'opponent', 'subject', 'winner', 'questions.question.answers']);

        return response()->json([
            'success' => true,
            'data' => $this->formatDuelDetails($duel, $user),
        ]);
    }

    public function state(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$duel->isParticipant($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $duel->load(['challenger', 'opponent', 'subject', 'questions.question.answers']);

        $currentQuestion = $duel->currentQuestion();
        $currentQuestion?->load('question.answers');

        return response()->json([
            'success' => true,
            'data' => [
                'duel' => $this->formatDuelDetails($duel, $user),
                'current_question' => $currentQuestion ? $this->formatCurrentQuestion($currentQuestion, $user) : null,
                'my_lives' => $duel->getPlayerLives($user),
                'opponent_lives' => $duel->getOpponentLives($user),
                'i_answered' => $currentQuestion?->hasPlayerAnswered($user) ?? false,
                'opponent_answered' => $currentQuestion ? $this->opponentAnswered($currentQuestion, $user) : false,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'opponent_id' => 'required|integer|exists:users,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'lives' => 'sometimes|integer|min:1|max:10',
            'time_per_question' => 'sometimes|integer|min:10|max:120',
        ]);

        $user = $request->user();
        $opponentId = $request->input('opponent_id');

        \Log::info('Creating duel', [
            'user_id' => $user->id,
            'opponent_id' => $opponentId,
            'subject_id' => $request->input('subject_id'),
        ]);

        if ($user->id === $opponentId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot challenge yourself',
            ], 400);
        }

        $opponent = User::findOrFail($opponentId);
        $subject = Subject::findOrFail($request->input('subject_id'));

        // Check if there's already an active duel between these users
        $existingDuel = Duel::where(function ($q) use ($user, $opponent) {
            $q->where('challenger_id', $user->id)->where('opponent_id', $opponent->id);
        })->orWhere(function ($q) use ($user, $opponent) {
            $q->where('challenger_id', $opponent->id)->where('opponent_id', $user->id);
        })->whereIn('status', ['pending', 'active'])->first();

        \Log::info('Existing duel check', [
            'found' => $existingDuel ? true : false,
            'duel_id' => $existingDuel?->id,
            'status' => $existingDuel?->status,
            'created_at' => $existingDuel?->created_at?->toISOString(),
            'updated_at' => $existingDuel?->updated_at?->toISOString(),
        ]);

        if ($existingDuel) {
            // Double-check the status (should be pending or active from query, but verify)
            if (!in_array($existingDuel->status, ['pending', 'active'])) {
                \Log::warning('Found duel with unexpected status', [
                    'duel_id' => $existingDuel->id,
                    'status' => $existingDuel->status,
                    'message' => 'Duel should have been filtered out by query',
                ]);
                // Skip this duel and allow creating new one
                $existingDuel = null;
            }
        }

        if ($existingDuel) {
            // Check if duel is stuck (active for more than 1 hour without update)
            if ($existingDuel->status === 'active' && $existingDuel->updated_at->lt(now()->subHour())) {
                // Mark as expired and determine winner based on remaining lives
                $winner = $existingDuel->determineWinner();
                $existingDuel->update([
                    'status' => 'expired',
                    'winner_id' => $winner?->id,
                    'completed_at' => now(),
                ]);

                $existingDuel->refresh();

                // Update stats
                if ($winner) {
                    $loser = $winner->id === $existingDuel->challenger_id ? $existingDuel->opponent : $existingDuel->challenger;
                    DuelStats::recordResult($winner, $loser, 'win');
                } else {
                    DuelStats::recordResult($existingDuel->challenger, $existingDuel->opponent, 'draw');
                }

                broadcast(new DuelEnded($existingDuel));
            } elseif ($existingDuel->status === 'pending' && $existingDuel->created_at->lt(now()->subHour())) {
                // Mark pending duel as expired if older than 1 hour
                $existingDuel->update([
                    'status' => 'expired',
                    'completed_at' => now(),
                ]);

                $existingDuel->refresh();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Active duel already exists with this user',
                    'debug' => [
                        'duel_id' => $existingDuel->id,
                        'status' => $existingDuel->status,
                        'created_at' => $existingDuel->created_at->toISOString(),
                        'updated_at' => $existingDuel->updated_at->toISOString(),
                    ],
                ], 400);
            }
        }

        $lives = $request->input('lives', 3);
        $timePerQuestion = $request->input('time_per_question', 30);

        $duel = Duel::create([
            'challenger_id' => $user->id,
            'opponent_id' => $opponent->id,
            'subject_id' => $subject->id,
            'initial_lives' => $lives,
            'time_per_question' => $timePerQuestion,
            'challenger_lives' => $lives,
            'opponent_lives' => $lives,
            'status' => 'pending',
        ]);

        // Broadcast invitation
        broadcast(new DuelInviteReceived($duel))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Duel invitation sent',
            'data' => $this->formatDuel($duel->load(['challenger', 'opponent', 'subject']), $user),
        ], 201);
    }

    public function accept(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$duel->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Duel is not pending',
            ], 400);
        }

        // Generate questions for the duel
        $questions = Question::where('subject_id', $duel->subject_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(20) // Get enough questions for a long duel
            ->get();

        if ($questions->count() < 5) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough questions available for this subject',
            ], 400);
        }

        // Create duel questions
        foreach ($questions as $index => $question) {
            DuelQuestion::create([
                'duel_id' => $duel->id,
                'question_id' => $question->id,
                'question_order' => $index,
                'started_at' => $index === 0 ? now() : null,
            ]);
        }

        // Update duel status
        $duel->update([
            'status' => 'active',
            'started_at' => now(),
        ]);

        $duel->load(['challenger', 'opponent', 'subject', 'questions.question.answers']);

        // Broadcast to both players
        broadcast(new DuelStarted($duel));

        // Schedule timeout for first question
        ProcessDuelQuestionTimeout::dispatch($duel->id, 0)
            ->delay(now()->addSeconds($duel->time_per_question + 2));

        return response()->json([
            'success' => true,
            'message' => 'Duel started',
            'data' => $this->formatDuelDetails($duel, $user),
        ]);
    }

    public function decline(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($duel->opponent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$duel->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Duel is not pending',
            ], 400);
        }

        $duel->update([
            'status' => 'declined',
            'completed_at' => now(),
        ]);

        $duel->refresh();

        // Notify challenger
        broadcast(new DuelDeclined($duel))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Duel declined',
        ]);
    }

    public function answer(Duel $duel, Request $request): JsonResponse
    {
        $request->validate([
            'answer_id' => 'required|integer|exists:answers,id',
        ]);

        $user = $request->user();

        if (!$duel->isParticipant($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$duel->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Duel is not active',
            ], 400);
        }

        $currentQuestion = $duel->currentQuestion();

        if (!$currentQuestion) {
            return response()->json([
                'success' => false,
                'message' => 'No current question',
            ], 400);
        }

        if ($currentQuestion->hasPlayerAnswered($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Already answered',
            ], 400);
        }

        $answer = Answer::findOrFail($request->input('answer_id'));

        // Verify answer belongs to current question
        if ($answer->question_id !== $currentQuestion->question_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid answer for this question',
            ], 400);
        }

        // Record the answer
        $currentQuestion->recordAnswer($user, $answer);

        // Broadcast that player answered (without revealing the answer)
        broadcast(new OpponentAnswered($duel, $user))->toOthers();

        // Check if both players answered
        $currentQuestion->refresh();

        if ($currentQuestion->bothAnswered()) {
            // Check if round is still pending (prevent race condition)
            if (!$currentQuestion->isPending()) {
                // Round already processed by another request
                return response()->json([
                    'success' => true,
                    'message' => 'Answer recorded',
                    'data' => [
                        'round_already_processed' => true,
                        'is_correct' => $answer->is_correct,
                    ],
                ]);
            }

            return $this->processRoundEnd($duel, $currentQuestion, $user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Answer recorded',
            'data' => [
                'waiting_for_opponent' => true,
                'is_correct' => $answer->is_correct,
            ],
        ]);
    }

    public function surrender(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$duel->isParticipant($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$duel->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Duel is not active',
            ], 400);
        }

        $winner = $duel->isChallenger($user) ? $duel->opponent : $duel->challenger;

        $duel->update([
            'status' => 'surrendered',
            'surrendered_by' => $user->id,
            'winner_id' => $winner->id,
            'completed_at' => now(),
        ]);

        $duel->refresh();

        \Log::info('Duel surrendered', [
            'duel_id' => $duel->id,
            'status' => $duel->status,
            'surrendered_by' => $user->id,
            'winner_id' => $winner->id,
        ]);

        // Update stats
        DuelStats::recordResult($winner, $user, 'win');

        // Broadcast end
        broadcast(new DuelEnded($duel));

        return response()->json([
            'success' => true,
            'message' => 'You surrendered',
            'data' => [
                'winner_id' => $winner->id,
            ],
        ]);
    }

    public function results(Duel $duel, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$duel->isParticipant($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $duel->load(['challenger', 'opponent', 'subject', 'winner', 'questions.question.answers']);

        $questions = $duel->questions->map(function ($dq) use ($duel) {
            return [
                'question_order' => $dq->question_order,
                'question' => [
                    'id' => $dq->question->id,
                    'text' => $dq->question->text,
                    'explanation' => $dq->question->explanation,
                ],
                'challenger_answer_id' => $dq->challenger_answer_id,
                'opponent_answer_id' => $dq->opponent_answer_id,
                'challenger_is_correct' => $dq->challenger_is_correct,
                'opponent_is_correct' => $dq->opponent_is_correct,
                'round_result' => $dq->round_result,
                'correct_answer_id' => $dq->question->answers->firstWhere('is_correct', true)?->id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'duel' => $this->formatDuelDetails($duel, $user),
                'questions' => $questions,
                'winner' => $duel->winner ? $this->formatUser($duel->winner) : null,
                'is_draw' => $duel->winner_id === null && $duel->status === 'completed',
            ],
        ]);
    }

    public function stats(int $opponentId, Request $request): JsonResponse
    {
        $user = $request->user();
        $opponent = User::findOrFail($opponentId);

        $stats = DuelStats::getStatsBetween($user, $opponent);

        return response()->json([
            'success' => true,
            'data' => $stats ? [
                'total_duels' => $stats->total_duels,
                'wins' => $stats->wins,
                'losses' => $stats->losses,
                'draws' => $stats->draws,
                'win_rate' => $stats->getWinRate(),
            ] : [
                'total_duels' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'win_rate' => 0,
            ],
        ]);
    }

    private function processRoundEnd(Duel $duel, DuelQuestion $currentQuestion, User $user): JsonResponse
    {
        // Finalize round atomically - returns false if already processed
        if (!$currentQuestion->finalizeRound()) {
            // Round already processed by another request
            return response()->json([
                'success' => true,
                'message' => 'Answer recorded',
                'data' => [
                    'round_already_processed' => true,
                ],
            ]);
        }

        // Apply life changes based on result
        $roundResult = $currentQuestion->round_result;

        if ($roundResult === 'challenger_wins') {
            $duel->decrementLives($duel->opponent);
        } elseif ($roundResult === 'opponent_wins') {
            $duel->decrementLives($duel->challenger);
        }

        // Refresh model to get updated lives
        $duel->refresh();

        // Broadcast round result
        broadcast(new RoundResult($duel, $currentQuestion));

        // Check if game is over
        if ($duel->checkGameOver()) {
            $winner = $duel->determineWinner();

            $duel->update([
                'status' => 'completed',
                'winner_id' => $winner?->id,
                'completed_at' => now(),
            ]);

            $duel->refresh();

            // Update stats
            if ($winner) {
                $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
                DuelStats::recordResult($winner, $loser, 'win');
            }

            broadcast(new DuelEnded($duel));

            return response()->json([
                'success' => true,
                'message' => 'Duel ended',
                'data' => [
                    'game_over' => true,
                    'winner_id' => $winner?->id,
                    'round_result' => $roundResult,
                    'challenger_lives' => $duel->challenger_lives,
                    'opponent_lives' => $duel->opponent_lives,
                ],
            ]);
        }

        // Move to next question
        $duel->increment('current_question_index');
        $duel->refresh(); // Ensure we have fresh data after increment

        \Log::info('Moving to next question', [
            'duel_id' => $duel->id,
            'new_index' => $duel->current_question_index,
        ]);

        $nextQuestion = $duel->currentQuestion();

        \Log::info('Next question lookup', [
            'duel_id' => $duel->id,
            'current_question_index' => $duel->current_question_index,
            'next_question_found' => $nextQuestion !== null,
            'next_question_id' => $nextQuestion?->id,
        ]);

        if ($nextQuestion) {
            $nextQuestion->update(['started_at' => now()]);
            $nextQuestion->load('question.answers');

            \Log::info('Broadcasting NewQuestion', [
                'duel_id' => $duel->id,
                'question_order' => $nextQuestion->question_order,
                'room_code' => $duel->room_code,
            ]);

            broadcast(new NewQuestion($duel, $nextQuestion));

            // Schedule timeout for next question
            ProcessDuelQuestionTimeout::dispatch($duel->id, $nextQuestion->question_order)
                ->delay(now()->addSeconds($duel->time_per_question + 2));

            return response()->json([
                'success' => true,
                'message' => 'Round completed',
                'data' => [
                    'game_over' => false,
                    'round_result' => $roundResult,
                    'challenger_lives' => $duel->challenger_lives,
                    'opponent_lives' => $duel->opponent_lives,
                    'next_question' => $this->formatCurrentQuestion($nextQuestion, $user),
                ],
            ]);
        }

        // No more questions - end the duel as a draw
        $winner = $duel->determineWinner();

        $duel->update([
            'status' => 'completed',
            'winner_id' => $winner?->id,
            'completed_at' => now(),
        ]);

        $duel->refresh();

        // Update stats
        if ($winner) {
            $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
            DuelStats::recordResult($winner, $loser, 'win');
        } else {
            // Both players have same lives - it's a draw
            DuelStats::recordResult($duel->challenger, $duel->opponent, 'draw');
        }

        broadcast(new DuelEnded($duel));

        return response()->json([
            'success' => true,
            'message' => 'Duel ended',
            'data' => [
                'game_over' => true,
                'winner_id' => $winner?->id,
                'round_result' => $roundResult,
                'challenger_lives' => $duel->challenger_lives,
                'opponent_lives' => $duel->opponent_lives,
            ],
        ]);
    }

    private function formatDuel(Duel $duel, User $user): array
    {
        return [
            'id' => $duel->id,
            'room_code' => $duel->room_code,
            'status' => $duel->status,
            'challenger' => $this->formatUser($duel->challenger),
            'opponent' => $this->formatUser($duel->opponent),
            'subject' => [
                'id' => $duel->subject->id,
                'name' => $duel->subject->name,
                'slug' => $duel->subject->slug,
                'icon' => $duel->subject->icon,
                'color' => $duel->subject->color,
            ],
            'initial_lives' => $duel->initial_lives,
            'time_per_question' => $duel->time_per_question,
            'winner_id' => $duel->winner_id,
            'is_my_challenge' => $duel->challenger_id === $user->id,
            'created_at' => $duel->created_at->toISOString(),
            'started_at' => $duel->started_at?->toISOString(),
            'completed_at' => $duel->completed_at?->toISOString(),
        ];
    }

    private function formatDuelDetails(Duel $duel, User $user): array
    {
        $data = $this->formatDuel($duel, $user);
        $data['challenger_lives'] = $duel->challenger_lives;
        $data['opponent_lives'] = $duel->opponent_lives;
        $data['current_question_index'] = $duel->current_question_index;
        $data['my_lives'] = $duel->getPlayerLives($user);
        $data['opponent_lives_for_me'] = $duel->getOpponentLives($user);

        return $data;
    }

    private function formatCurrentQuestion(DuelQuestion $dq, User $user): array
    {
        return [
            'id' => $dq->id,
            'question_order' => $dq->question_order,
            'question' => [
                'id' => $dq->question->id,
                'text' => $dq->question->text,
                'answers' => $dq->question->answers->map(fn($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ])->toArray(),
            ],
            'started_at' => $dq->started_at?->toISOString(),
            'i_answered' => $dq->hasPlayerAnswered($user),
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'photo_url' => $user->photo_url,
        ];
    }

    private function opponentAnswered(DuelQuestion $dq, User $user): bool
    {
        $duel = $dq->duel;
        if ($duel->isChallenger($user)) {
            return $dq->opponent_answer_id !== null;
        }
        return $dq->challenger_answer_id !== null;
    }
}
