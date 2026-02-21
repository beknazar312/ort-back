<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->getOrCreateSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'streak_notifications_enabled' => $settings->streak_notifications_enabled,
                'preferred_language' => $settings->preferred_language,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'streak_notifications_enabled' => 'sometimes|boolean',
            'preferred_language' => 'sometimes|string|in:ru,kg',
        ]);

        $user = $request->user();
        $settings = $user->getOrCreateSettings();

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'streak_notifications_enabled' => $settings->streak_notifications_enabled,
                'preferred_language' => $settings->preferred_language,
            ],
        ]);
    }
}
