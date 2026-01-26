<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserStatsController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all completed attempts
        $attempts = TestAttempt::where('user_id', $user->id)
            ->completed()
            ->get();

        // Practice stats
        $practiceAttempts = $attempts->where('mode', 'practice');
        $totalPractice = $practiceAttempts->count();
        $correctPractice = $practiceAttempts->sum('correct_answers');

        // Test stats
        $testAttempts = $attempts->where('mode', 'test');
        $totalTests = $testAttempts->count();
        $averageTestScore = $testAttempts->count() > 0
            ? round($testAttempts->avg('score'), 1)
            : 0;

        // Calculate streak (consecutive days with practice)
        $streak = $this->calculateStreak($user->id);

        // Stats by subject
        $bySubject = TestAttempt::where('user_id', $user->id)
            ->completed()
            ->whereNotNull('subject_id')
            ->with('subject')
            ->get()
            ->groupBy('subject_id')
            ->map(function ($subjectAttempts) {
                $subject = $subjectAttempts->first()->subject;
                return [
                    'subject' => [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'slug' => $subject->slug,
                    ],
                    'total_questions' => $subjectAttempts->sum('total_questions'),
                    'correct_answers' => $subjectAttempts->sum('correct_answers'),
                    'average_score' => round($subjectAttempts->avg('score'), 1),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_questions_solved' => $practiceAttempts->sum('total_questions') + $testAttempts->sum('total_questions'),
                'correct_answers' => $correctPractice + $testAttempts->sum('correct_answers'),
                'practice' => [
                    'total' => $totalPractice,
                    'correct' => $correctPractice,
                ],
                'tests' => [
                    'total' => $totalTests,
                    'average_score' => $averageTestScore,
                ],
                'streak' => $streak,
                'by_subject' => $bySubject,
            ],
        ]);
    }

    public function testHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $attempts = TestAttempt::where('user_id', $user->id)
            ->completed()
            ->where('mode', 'test')
            ->with(['test', 'subject'])
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attempts->map(fn ($attempt) => [
                'id' => $attempt->id,
                'test' => $attempt->test ? [
                    'id' => $attempt->test->id,
                    'name' => $attempt->test->name,
                ] : null,
                'subject' => $attempt->subject ? [
                    'id' => $attempt->subject->id,
                    'name' => $attempt->subject->name,
                ] : null,
                'total_questions' => $attempt->total_questions,
                'correct_answers' => $attempt->correct_answers,
                'score' => $attempt->score,
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'completed_at' => $attempt->completed_at->toISOString(),
            ]),
        ]);
    }

    private function calculateStreak(int $userId): int
    {
        $dates = TestAttempt::where('user_id', $userId)
            ->completed()
            ->selectRaw('DATE(completed_at) as date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->map(fn ($d) => \Carbon\Carbon::parse($d));

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $today = now()->startOfDay();
        $checkDate = $today;

        // Check if there's activity today or yesterday to count the streak
        $firstDate = $dates->first();
        if ($firstDate->diffInDays($today) > 1) {
            return 0;
        }

        foreach ($dates as $date) {
            if ($date->isSameDay($checkDate) || $date->isSameDay($checkDate->copy()->subDay())) {
                $streak++;
                $checkDate = $date;
            } else {
                break;
            }
        }

        return $streak;
    }
}
