<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    protected string $botToken;
    protected string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
    }

   public function webhook(Request $request)
    {

        $update = $request->all();
        $message = $update['message'] ?? null;
        if (!$message) {
            return response('ok', 200);
        }

        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        if (strtolower($text) === '/start') {
            $this->sendMessage($chatId, 'Welcome to the bot! How can I assist you today?');
        } else {
            $this->sendMessage($chatId, 'You said: ' . $text);
        }
        return response()->json([
            'ok' => true,
        ]);
    }
    protected function sendMessage($chatId, $text)
    {
        return Http::post($this->apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ])->json();
    }
}