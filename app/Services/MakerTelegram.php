<?php

namespace App\Services;

use App\Models\MakerTelegramLink;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MakerTelegram
{
    public function configured(): bool
    {
        $token = $this->token();
        $adminToken = (string) config('services.telegram.bot_token');

        return TelegramBot::validToken($token)
            && strstr($token, ':', true) !== strstr($adminToken, ':', true)
            && preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/D', $this->username()) === 1;
    }

    public function verifyConfiguration(): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $answer = TelegramBot::request($this->token(), 'getMe', []);

        return $answer?->json('ok') === true
            && $answer->json('result.is_bot') === true
            && strcasecmp((string) $answer->json('result.username'), $this->username()) === 0;
    }

    public function connectUrl(User $maker): string
    {
        abort_unless($this->canConnect($maker), 403);
        abort_unless($this->configured(), 503);

        $code = bin2hex(random_bytes(24));
        DB::transaction(function () use ($maker, $code): void {
            $account = User::query()->lockForUpdate()->findOrFail($maker->id);
            abort_unless($this->canConnect($account), 403);

            $link = MakerTelegramLink::where('user_id', $account->id)->first();
            if ($link?->chat_id !== null) {
                throw ValidationException::withMessages(['telegram' => 'Спочатку від’єднайте поточний чат Telegram.']);
            }

            MakerTelegramLink::updateOrCreate(['user_id' => $account->id], [
                'pairing_hash' => hash('sha256', $code),
                'pairing_expires_at' => now()->addMinutes(15),
            ]);
        });

        return 'https://t.me/'.$this->username().'?start='.$code;
    }

    public function disconnect(User $maker): void
    {
        DB::transaction(function () use ($maker): void {
            $account = User::query()->lockForUpdate()->findOrFail($maker->id);
            abort_unless($this->canConnect($account), 403);

            MakerTelegramLink::where('user_id', $account->id)->delete();
        });
    }

    public function process(): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $fingerprint = hash('sha256', $this->token());

        return (int) Cache::lock('telegram:makers:process:'.$fingerprint, 180)->get(function () use ($fingerprint): int {
            $this->poll('telegram:makers:offset:'.$fingerprint);

            return app(TelegramAlerts::class)->processMakers();
        });
    }

    private function poll(string $offsetKey): void
    {
        $response = TelegramBot::request($this->token(), 'getUpdates', [
            'offset' => (int) Cache::get($offsetKey, 0),
            'allowed_updates' => ['message'],
            'limit' => 10,
        ]);
        if ($response?->json('ok') !== true) {
            return;
        }

        foreach ($response->json('result', []) as $update) {
            $this->answer($update);
            Cache::put($offsetKey, (int) $update['update_id'] + 1, now()->addDays(2));
        }
    }

    private function answer(array $update): void
    {
        $chatId = TelegramBot::privateChatId($update);
        if ($chatId === null || data_get($update, 'message.from.is_bot') === true
            || (string) data_get($update, 'message.from.id', $chatId) !== $chatId) {
            return;
        }

        $text = trim((string) data_get($update, 'message.text'));
        [$command, $code] = array_pad(preg_split('/\s+/', $text, 2), 2, '');
        $command = strtolower($command);
        $command = str_ends_with($command, '@'.strtolower($this->username())) ? strstr($command, '@', true) : $command;
        $help = "🧺 <b>Рідна Віра · Замовлення майстрам</b>\n\n"
            .'Щоб отримувати замовлення, відкрийте «Замовлення на мої вироби» в кабінеті й натисніть «Підключити Telegram».'
            ."\n/status — перевірити підключення";

        $message = match ($command) {
            '/start' => $code !== '' ? $this->pair($code, $chatId) : $help,
            '/help' => $help,
            '/status' => $this->linked($chatId)
                ? '✅ <b>Telegram під’єднано до кабінету майстра.</b>'
                : 'Telegram ще не під’єднано. Відкрийте налаштування в кабінеті майстра.',
            default => null,
        };
        if ($message !== null) {
            TelegramBot::send($this->token(), $chatId, $message);
        }
    }

    private function pair(string $code, string $chatId): string
    {
        $expired = 'Посилання недійсне або вже використане. Відкрийте кабінет майстра та створіть нове посилання.';
        if (! preg_match('/^[a-f0-9]{48}$/D', $code)) {
            return $expired;
        }

        $hash = hash('sha256', $code);
        $candidate = MakerTelegramLink::where('pairing_hash', $hash)->first();
        if ($candidate === null) {
            return $expired;
        }

        try {
            return DB::transaction(function () use ($candidate, $hash, $chatId, $expired): string {
                $maker = User::query()->lockForUpdate()->find($candidate->user_id);
                $link = MakerTelegramLink::whereKey($candidate->id)
                    ->where('pairing_hash', $hash)->whereNotNull('pairing_expires_at')
                    ->where('pairing_expires_at', '>', now())->whereNull('chat_id')->lockForUpdate()->first();
                if ($maker === null || ! $this->canConnect($maker) || $link === null) {
                    return $expired;
                }

                $link->update(['chat_id' => $chatId, 'pairing_hash' => null, 'pairing_expires_at' => null]);

                return '✅ <b>Telegram під’єднано.</b> Сюди надходитимуть замовлення на ваші вироби.';
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return 'Цей Telegram уже під’єднано до іншого кабінету майстра.';
        }
    }

    private function linked(string $chatId): bool
    {
        return MakerTelegramLink::where('chat_id', $chatId)->whereHas('user', fn ($query) => $query
            ->where('role', 'maker')->where('is_active', true)->whereNotNull('email_verified_at'))->exists();
    }

    private function canConnect(User $maker): bool
    {
        return $maker->roleName() === 'maker' && $maker->is_active && $maker->hasVerifiedEmail();
    }

    private function token(): string
    {
        return (string) config('services.maker_telegram.bot_token');
    }

    private function username(): string
    {
        return ltrim(trim((string) config('services.maker_telegram.bot_username')), '@');
    }
}
