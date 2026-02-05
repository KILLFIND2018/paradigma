<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    private $aiServiceUrl;

    public function __construct()
    {
        $this->aiServiceUrl = env('AI_SERVICE_URL', 'http://llm-service:5000');
    }

    public function index()
    {
        return view('chat');
    }

    public function sendMessage(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'user_id' => 'sometimes|string',
                'generate_audio' => 'boolean'
            ]);

            $userId = $validated['user_id'] ?? 'user_' . uniqid();

            // Отправляем в AI сервис
            $response = Http::timeout(30)
                ->post($this->aiServiceUrl . '/generate', [
                    'message' => $validated['message'],
                    'user_id' => $userId,
                    'generate_audio' => $validated['generate_audio'] ?? false
                ]);

            if ($response->failed()) {
                Log::error('AI service error', ['response' => $response->body()]);

                return response()->json([
                    'success' => false,
                    'error' => 'AI service unavailable',
                    'fallback_text' => "Извините, сервис временно недоступен."
                ], 503);
            }

            $aiResponse = $response->json();

            if ($aiResponse['success']) {
                // Сохраняем в историю
                $this->saveToHistory($userId, [
                    'user_message' => $validated['message'],
                    'ai_response' => $aiResponse['text'],
                    'timestamp' => now()->toDateTimeString()
                ]);

                // Формируем полный ответ
                $responseData = [
                    'text' => $aiResponse['text'],
                    'user_id' => $userId,
                    'timestamp' => now()->toDateTimeString()
                ];

                // Добавляем аудио URL если есть
                if (!empty($aiResponse['audio_filename'])) {
                    $responseData['audio_url'] = $this->aiServiceUrl . '/audio/' . $aiResponse['audio_filename'];
                }

                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $aiResponse['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Chat error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getQuestions()
    {
        // Загружаем вопросы из файла или базы
        $questions = [
            ["question" => "Привет", "answer" => "Здравствуйте! Чем могу помочь?"],
            ["question" => "Как дела?", "answer" => "Всё отлично, спасибо! А у вас?"],
            ["question" => "Что ты умеешь?", "answer" => "Я могу общаться с вами и отвечать на вопросы."]
        ];

        return response()->json($questions);
    }

    public function getHistory($user_id)
    {
        try {
            // Получаем историю из кеша (Redis)
            $history = Cache::get("chat_history_{$user_id}", []);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user_id,
                    'history' => $history,
                    'total_messages' => count($history)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function saveToHistory($userId, $messageData)
    {
        $key = "chat_history_{$userId}";
        $history = Cache::get($key, []);

        // Добавляем новое сообщение
        $history[] = $messageData;

        // Ограничиваем историю последними 100 сообщениями
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        // Сохраняем на 7 дней
        Cache::put($key, $history, now()->addDays(7));
    }
}
