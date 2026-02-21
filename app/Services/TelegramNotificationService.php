<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected string $botToken;
    protected string $webAppUrl;

    private const MESSAGES = [
        'evening' => [
            'ru' => "🔥 Привет! Твой стрик в {streak} дней ждет. Зайди на пару минут, чтобы огонек не погас!",
            'kg' => "🔥 Салам! Сенин {streak} күндүк серияң күтүп жатат. Өчүп калбасын деп бир-эки мүнөткө кир!",
        ],
        'last_chance' => [
            'ru' => "😱 SOS! Твоя серия из {streak} дней сгорит через час! Скорее спасай её!",
            'kg' => "😱 SOS! Сенин {streak} күндүк серияң бир саатта өчүп калат! Тезирээк сакта!",
        ],
        'milestone' => [
            'ru' => "🎉 Ого! Ты занимаешься уже {streak} дней подряд! Это мощная заявка на высокий балл ОРТ. Так держать!",
            'kg' => "🎉 Ого! Сен катар менен {streak} күн окудуң! Бул ОРТден жогорку балл алуу үчүн күчтүү негиз. Ушундай эле улант!",
        ],
    ];

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->webAppUrl = config('services.telegram.webapp_url');
    }

    public function sendStreakReminder(User $user, int $streak, string $scenario): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        $language = $this->normalizeLanguage($user->getPreferredLanguage());
        $messageTemplate = self::MESSAGES[$scenario][$language] ?? self::MESSAGES[$scenario]['ru'];
        $message = str_replace('{streak}', (string)$streak, $messageTemplate);

        $keyboard = $this->buildOpenAppKeyboard($language);

        $success = $this->sendMessage($user->telegram_id, $message, $keyboard);

        $this->logNotification($user, 'streak_reminder', $scenario, $streak, $success);

        return $success;
    }

    public function sendMilestoneNotification(User $user, int $streak): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        $language = $this->normalizeLanguage($user->getPreferredLanguage());
        $messageTemplate = self::MESSAGES['milestone'][$language] ?? self::MESSAGES['milestone']['ru'];
        $message = str_replace('{streak}', (string)$streak, $messageTemplate);

        $keyboard = $this->buildOpenAppKeyboard($language);

        $success = $this->sendMessage($user->telegram_id, $message, $keyboard);

        $this->logNotification($user, 'milestone', null, $streak, $success);

        return $success;
    }

    public function wasNotificationSentToday(User $user, string $type, ?string $scenario = null): bool
    {
        $query = NotificationLog::where('user_id', $user->id)
            ->forToday()
            ->successful()
            ->ofType($type);

        if ($scenario) {
            $query->ofScenario($scenario);
        }

        return $query->exists();
    }

    protected function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): bool
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];

            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $params);

            if (!$response->successful() || !$response->json('ok')) {
                Log::warning('Telegram notification failed', [
                    'chat_id' => $chatId,
                    'response' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Telegram notification error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function buildOpenAppKeyboard(string $language): array
    {
        $buttonText = $language === 'kg' ? '🚀 Колдонмону ач' : '🚀 Открыть приложение';

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => $buttonText,
                        'web_app' => [
                            'url' => $this->webAppUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function normalizeLanguage(string $language): string
    {
        // Support both 'kg' and 'ky' for Kyrgyz
        if (in_array($language, ['kg', 'ky', 'kir'])) {
            return 'kg';
        }
        return 'ru';
    }

    protected function logNotification(
        User $user,
        string $type,
        ?string $scenario,
        int $streak,
        bool $success,
        ?string $errorMessage = null
    ): void {
        NotificationLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'scenario' => $scenario,
            'streak_count' => $streak,
            'sent_successfully' => $success,
            'error_message' => $errorMessage,
            'notification_date' => today(),
        ]);
    }
}
