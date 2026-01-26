<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private TelegramAuthService $telegramAuthService
    ) {}

    public function telegram(Request $request): JsonResponse
    {
        $request->validate([
            'init_data' => 'required|string',
        ]);

        $validatedData = $this->telegramAuthService->validateInitData($request->init_data);

        if (!$validatedData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Telegram authentication data',
            ], 401);
        }

        $telegramUser = $this->telegramAuthService->extractUser($validatedData);

        if (!$telegramUser || !isset($telegramUser['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'User data not found',
            ], 401);
        }

        $user = User::findByTelegramId($telegramUser['id']);

        if ($user) {
            $user->updateFromTelegram($telegramUser);
        } else {
            $user = User::createFromTelegram($telegramUser);
        }

        $token = $user->createToken('telegram-mini-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $request->name,
            'first_name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный email или пароль',
            ], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telegram_id' => $user->telegram_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'photo_url' => $user->photo_url,
            'is_premium' => $user->is_premium,
            'is_admin' => $user->is_admin,
        ];
    }
}
