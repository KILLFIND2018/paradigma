<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ChatController;

// Главная страница с чатом и 3D моделью
Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::get('/chat', [ChatController::class, 'index'])->name('chat');

// API endpoints для фронтенда
Route::prefix('api')->group(function () {
    Route::post('/chat/send', [ChatController::class, 'sendMessage']);
    Route::get('/chat/questions', [ChatController::class, 'getQuestions']);
    Route::get('/chat/history/{user_id}', [ChatController::class, 'getHistory']);
});
