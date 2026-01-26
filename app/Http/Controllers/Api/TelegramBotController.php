<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    protected string $botToken;
    protected string $webAppUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->webAppUrl = config('services.telegram.webapp_url');
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        $update = $request->all();

        Log::info('Telegram webhook received', $update);

        // Handle /start command
        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
            $chatId = $update['message']['chat']['id'];
            $firstName = $update['message']['from']['first_name'] ?? 'Друг';

            if (str_starts_with($text, '/start')) {
                $this->sendStartMessage($chatId, $firstName);
            }
        }

        // Handle callback queries (button presses)
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $this->answerCallbackQuery($callbackQuery['id']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Send start message with Mini App button
     */
    protected function sendStartMessage(int $chatId, string $firstName): void
    {
        $message = "Привет, {$firstName}! 👋\n\n";
        $message .= "Добро пожаловать в ORT - приложение для подготовки к тестам!\n\n";
        $message .= "🎯 Практикуйся по предметам\n";
        $message .= "📝 Проходи тесты\n";
        $message .= "⚔️ Соревнуйся с друзьями в дуэлях\n\n";
        $message .= "Нажми кнопку ниже, чтобы начать:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 Открыть приложение',
                        'web_app' => [
                            'url' => $this->webAppUrl
                        ]
                    ]
                ]
            ]
        ];

        $this->sendMessage($chatId, $message, $keyboard);
    }

    /**
     * Send message via Telegram API
     */
    protected function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $params);
    }

    /**
     * Answer callback query
     */
    protected function answerCallbackQuery(string $callbackQueryId): void
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
        ]);
    }

    /**
     * Set webhook URL (call this once to register webhook)
     */
    public function setWebhook(Request $request)
    {
        $webhookUrl = $request->input('url') ?? url('/api/telegram/webhook');

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/setWebhook", [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        return response()->json($response->json());
    }

    /**
     * Get current webhook info
     */
    public function getWebhookInfo()
    {
        $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getWebhookInfo");

        return response()->json($response->json());
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook()
    {
        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/deleteWebhook");

        return response()->json($response->json());
    }
}
