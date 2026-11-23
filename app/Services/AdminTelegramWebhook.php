<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

final class AdminTelegramWebhook
{
    public const PATH = '/telegram/admin/webhook';

    private const SETTING = 'telegram_admin_webhook_fingerprint';

    public function enabled(): bool
    {
        $fingerprint = SiteSetting::read(self::SETTING);
        $secret = is_string($fingerprint) ? $this->secret() : null;

        return $secret !== null && hash_equals($fingerprint, hash('sha256', $secret));
    }

    public function accepts(#[\SensitiveParameter] ?string $header): bool
    {
        $secret = $header !== null ? $this->secret() : null;

        return $secret !== null && hash_equals($secret, $header) && $this->enabled();
    }

    public function install(): ?string
    {
        return $this->configure(webhook: true);
    }

    public function switchToPolling(): ?string
    {
        return $this->configure(webhook: false);
    }

    /** @return array{mode: string, pending: int, error_at: int}|null */
    public function status(): ?array
    {
        $info = $this->info();
        if ($info === null) {
            return null;
        }

        return [
            'mode' => match ($info['url']) {
                '' => $this->enabled() ? 'missing' : 'polling',
                $this->url() => $this->enabled() ? 'webhook' : 'unconfirmed',
                default => 'external',
            },
            'pending' => max(0, (int) ($info['pending_update_count'] ?? 0)),
            'error_at' => max(0, (int) ($info['last_error_date'] ?? 0)),
        ];
    }

    private function configure(bool $webhook): ?string
    {
        $secret = $webhook ? $this->secret() : null;
        if ($webhook && ($secret === null || ! $this->hasHttpsAppUrl())) {
            return 'Для webhook потрібна адреса сайту з HTTPS у APP_URL і чинний APP_KEY.';
        }
        if ($webhook && ! Route::has('telegram.admin.webhook')) {
            return 'Оновіть кеш маршрутів командою php artisan route:cache.';
        }

        $info = $this->info();
        if ($info === null) {
            return 'Не вдалося перевірити налаштування бота в Telegram.';
        }
        if ($info['url'] !== '' && $info['url'] !== $this->url()) {
            return 'У Telegram зареєстровано webhook для іншої адреси.';
        }

        if ($webhook) {
            $response = TelegramBot::request($this->botToken(), 'setWebhook', [
                'url' => $this->url(),
                'secret_token' => $secret,
                'allowed_updates' => ['message', 'callback_query'],
            ]);
            if ($response?->json('ok') !== true) {
                return 'Telegram відхилив реєстрацію webhook. Перевірте, чи доступна адреса сайту через HTTPS.';
            }
        } elseif ($info['url'] !== '') {
            $response = TelegramBot::request($this->botToken(), 'deleteWebhook', []);
            if ($response?->json('ok') !== true) {
                return 'Telegram не підтвердив перехід до опитування бота.';
            }
        }

        SiteSetting::write(self::SETTING, $webhook ? hash('sha256', $secret) : null);

        return null;
    }

    /** @return array{url: string, pending_update_count?: int, last_error_date?: int}|null */
    private function info(): ?array
    {
        $response = TelegramBot::request($this->botToken(), 'getWebhookInfo', []);
        $result = $response?->json('result');

        return $response?->json('ok') === true && is_array($result) && is_string($result['url'] ?? null) ? $result : null;
    }

    private function secret(): ?string
    {
        if (! app(TelegramAlerts::class)->configured() || blank(config('app.key'))) {
            return null;
        }

        return hash_hmac('sha256', "ridna-vira:telegram:admin-webhook\n".$this->botToken()."\n".$this->url(), Crypt::getKey());
    }

    private function botToken(): string
    {
        return (string) config('services.telegram.bot_token');
    }

    private function hasHttpsAppUrl(): bool
    {
        $url = (string) config('app.url');
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/').self::PATH;
    }
}
