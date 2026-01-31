<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\UserStatsController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\DuelController;
use App\Http\Controllers\Api\MarathonController;
use App\Http\Controllers\Api\TelegramBotController;
use Illuminate\Support\Facades\Route;

// Telegram Bot Webhook (public, no auth required)
Route::prefix('telegram')->group(function () {
    Route::post('/webhook', [TelegramBotController::class, 'webhook']);
    Route::post('/set-webhook', [TelegramBotController::class, 'setWebhook']);
    Route::get('/webhook-info', [TelegramBotController::class, 'getWebhookInfo']);
    Route::post('/delete-webhook', [TelegramBotController::class, 'deleteWebhook']);
});

Route::prefix('auth')->group(function () {
    Route::post('/telegram', [AuthController::class, 'telegram']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Public routes
Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/subjects/{slug}', [SubjectController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Interactive practice
    Route::get('/subjects/{slug}/questions/random', [QuestionController::class, 'random']);
    Route::post('/questions/{id}/answer', [QuestionController::class, 'answer']);

    // Tests
    Route::get('/tests', [TestController::class, 'index']);
    Route::get('/tests/{id}', [TestController::class, 'show']);
    Route::post('/tests/{id}/start', [TestController::class, 'start']);
    Route::get('/test-attempts/{id}/resume', [TestController::class, 'resume']);
    Route::post('/test-attempts/{id}/submit', [TestController::class, 'submit']);
    Route::get('/test-attempts/{id}/results', [TestController::class, 'results']);

    // User statistics
    Route::get('/me/stats', [UserStatsController::class, 'stats']);
    Route::get('/me/test-history', [UserStatsController::class, 'testHistory']);

    // Friends
    Route::prefix('friends')->group(function () {
        Route::get('/', [FriendController::class, 'index']);
        Route::get('/search', [FriendController::class, 'search']);
        Route::get('/requests', [FriendController::class, 'requests']);
        Route::post('/request', [FriendController::class, 'sendRequest']);
        Route::post('/accept/{friendship}', [FriendController::class, 'accept']);
        Route::post('/reject/{friendship}', [FriendController::class, 'reject']);
        Route::delete('/{friendId}', [FriendController::class, 'remove']);
    });

    // Duels
    Route::prefix('duels')->group(function () {
        Route::get('/', [DuelController::class, 'index']);
        Route::get('/pending', [DuelController::class, 'pending']);
        Route::get('/{duel}', [DuelController::class, 'show']);
        Route::get('/{duel}/state', [DuelController::class, 'state']);
        Route::post('/', [DuelController::class, 'store']);
        Route::post('/{duel}/accept', [DuelController::class, 'accept']);
        Route::post('/{duel}/decline', [DuelController::class, 'decline']);
        Route::post('/{duel}/answer', [DuelController::class, 'answer']);
        Route::post('/{duel}/surrender', [DuelController::class, 'surrender']);
        Route::get('/{duel}/results', [DuelController::class, 'results']);
        Route::get('/stats/{opponentId}', [DuelController::class, 'stats']);
    });

    // Marathon
    Route::prefix('marathon')->group(function () {
        Route::post('/{slug}/start', [MarathonController::class, 'start']);
        Route::get('/{session}/question', [MarathonController::class, 'getQuestion']);
        Route::post('/{session}/answer', [MarathonController::class, 'submitAnswer']);
        Route::post('/{session}/complete', [MarathonController::class, 'complete']);
        Route::get('/{slug}/personal-best', [MarathonController::class, 'getPersonalBest']);
    });
});
