<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StreakService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class SendStreakNotifications extends Command
{
    protected $signature = 'streak:notify {--scenario=evening : Notification scenario (evening or last_chance)}';

    protected $description = 'Send streak reminder notifications to eligible users';

    public function __construct(
        protected StreakService $streakService,
        protected TelegramNotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scenario = $this->option('scenario');

        if (!in_array($scenario, ['evening', 'last_chance'])) {
            $this->error("Invalid scenario: {$scenario}. Must be 'evening' or 'last_chance'.");
            return self::FAILURE;
        }

        $this->info("Starting streak notifications for scenario: {$scenario}");

        $users = $this->getEligibleUsers($scenario);
        $total = $users->count();
        $sent = 0;
        $skipped = 0;

        $this->info("Found {$total} eligible users.");

        foreach ($users as $user) {
            // Check if user has streak >= 1
            $streak = $this->streakService->calculateStreak($user->id);

            if ($streak < 1) {
                $skipped++;
                continue;
            }

            // Check if user was already active today
            if ($this->streakService->wasActiveToday($user->id)) {
                $skipped++;
                continue;
            }

            // For last_chance, check if evening notification was already sent
            if ($scenario === 'last_chance') {
                if (!$this->notificationService->wasNotificationSentToday($user, 'streak_reminder', 'evening')) {
                    $skipped++;
                    continue;
                }
            }

            // Check if this specific notification was already sent
            if ($this->notificationService->wasNotificationSentToday($user, 'streak_reminder', $scenario)) {
                $skipped++;
                continue;
            }

            // Send notification
            $success = $this->notificationService->sendStreakReminder($user, $streak, $scenario);

            if ($success) {
                $sent++;
                $this->line("  Sent to user #{$user->id} (streak: {$streak})");
            } else {
                $this->warn("  Failed to send to user #{$user->id}");
            }

            // Rate limiting: 50ms between messages to avoid Telegram API limits
            usleep(50000);
        }

        $this->info("Completed: {$sent} sent, {$skipped} skipped out of {$total} users.");

        return self::SUCCESS;
    }

    protected function getEligibleUsers(string $scenario): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereNotNull('telegram_id')
            ->whereDoesntHave('settings', function ($query) {
                $query->where('streak_notifications_enabled', false);
            })
            ->get();
    }
}
