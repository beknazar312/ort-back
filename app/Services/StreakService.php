<?php

namespace App\Services;

use App\Models\MarathonSession;
use App\Models\TestAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StreakService
{
    private const MILESTONE_DAYS = [7, 14, 30, 50, 100];

    public function calculateStreak(int $userId): int
    {
        $dates = $this->getActivityDates($userId);

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

    public function wasActiveToday(int $userId): bool
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // Check TestAttempts
        $hasTestAttempt = TestAttempt::where('user_id', $userId)
            ->completed()
            ->whereBetween('completed_at', [$todayStart, $todayEnd])
            ->exists();

        if ($hasTestAttempt) {
            return true;
        }

        // Check MarathonSessions
        $hasMarathonSession = MarathonSession::where('user_id', $userId)
            ->completed()
            ->whereBetween('completed_at', [$todayStart, $todayEnd])
            ->exists();

        return $hasMarathonSession;
    }

    public function isMilestone(int $streak): bool
    {
        return in_array($streak, self::MILESTONE_DAYS);
    }

    public function getMilestoneDays(): array
    {
        return self::MILESTONE_DAYS;
    }

    private function getActivityDates(int $userId): Collection
    {
        // Get dates from TestAttempts
        $testDates = TestAttempt::where('user_id', $userId)
            ->completed()
            ->selectRaw('DATE(completed_at) as date')
            ->pluck('date');

        // Get dates from MarathonSessions
        $marathonDates = MarathonSession::where('user_id', $userId)
            ->completed()
            ->selectRaw('DATE(completed_at) as date')
            ->pluck('date');

        // Merge and get unique dates
        return $testDates->merge($marathonDates)
            ->unique()
            ->map(fn ($d) => Carbon::parse($d))
            ->sortByDesc(fn ($d) => $d)
            ->values();
    }
}
