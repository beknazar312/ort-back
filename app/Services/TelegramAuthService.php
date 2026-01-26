<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelegramAuthService
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
    }

    public function validateInitData(string $initData): ?array
    {
        if (empty($initData)) {
            return null;
        }

        parse_str($initData, $data);

        if (!isset($data['hash'])) {
            Log::warning('Telegram auth: hash not found in initData');
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        if (!hash_equals($calculatedHash, $hash)) {
            Log::warning('Telegram auth: hash validation failed');
            return null;
        }

        if (isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];
            $maxAge = config('services.telegram.auth_max_age', 86400);

            if (time() - $authDate > $maxAge) {
                Log::warning('Telegram auth: auth_date is too old');
                return null;
            }
        }

        if (isset($data['user'])) {
            $data['user'] = json_decode($data['user'], true);
        }

        return $data;
    }

    public function extractUser(array $validatedData): ?array
    {
        return $validatedData['user'] ?? null;
    }
}
