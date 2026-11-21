<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class TelegramBot
{
    public static function validToken(?string $token): bool
    {
        return preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/D', (string) $token) === 1;
    }

    public static function privateChatId(array $update): ?string
    {
        if (data_get($update, 'message.chat.type') !== 'private') {
            return null;
        }

        $id = (string) data_get($update, 'message.chat.id');

        return preg_match('/^[1-9][0-9]*$/D', $id) === 1 ? $id : null;
    }

    public static function request(string $token, string $method, array $data): ?Response
    {
        if (! self::validToken($token)) {
            return null;
        }

        try {
            return Http::acceptJson()->connectTimeout(3)->timeout(5)->withoutRedirecting()
                ->post("https://api.telegram.org/bot{$token}/{$method}", $data);
        } catch (ConnectionException) {
            return null;
        }
    }

    public static function send(string $token, string $chatId, string $message): ?Response
    {
        return self::request($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'protect_content' => true,
        ]);
    }
}
