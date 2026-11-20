<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

final class TelegramAlerts
{
    private const TEXT_LIMIT = 1800;

    private function hasBotToken(): bool
    {
        return preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/D', (string) config('services.telegram.bot_token')) === 1;
    }

    public function configured(): bool
    {
        return $this->hasBotToken() && preg_match('/^[1-9][0-9]*$/D', (string) config('services.telegram.chat_id')) === 1;
    }

    /**
     * Named lines become bold field labels. Numeric keys are free text.
     *
     * @param  array<int|string, string>  $lines
     */
    public function enqueue(string $eventKey, string $heading, array $lines = [], ?string $adminPath = null): void
    {
        if (! $this->configured()) {
            return;
        }

        try {
            $now = now();
            $rows = [];
            foreach ($this->format($heading, $lines, $adminPath) as $index => $message) {
                $rows[] = [
                    'event_key' => $index === 0 ? $eventKey : $eventKey.':part:'.($index + 1),
                    'message' => Crypt::encryptString($message),
                    'available_at' => $now,
                    'created_at' => $now,
                ];
            }
            if (DB::table('telegram_alerts')->insertOrIgnore($rows) > 0) {
                $eventKeys = array_column($rows, 'event_key');
                DB::afterCommit(fn () => defer(function () use ($eventKeys): void {
                    try {
                        $this->deliver($eventKeys);
                    } catch (Throwable) {
                        Log::warning('Immediate Telegram delivery failed.');
                    }
                })->always());
            }
        } catch (Throwable) {
            Log::warning('Unable to queue a Telegram notification.', ['event' => $eventKey]);
        }
    }

    public function productAdded(Product $product, User $author, ?string $description = null): void
    {
        if (! $this->configured()) {
            return;
        }

        $isMakerProduct = $product->maker_id !== null;
        $event = $product->wasRecentlyCreated ? 'created' : 'updated:'.Str::uuid();
        $heading = match (true) {
            ! $isMakerProduct => '🧺 Новий товар додано в адмінці',
            $product->wasRecentlyCreated => '🧺 Новий виріб на модерації',
            default => '🧺 Оновлений виріб на модерації',
        };
        $category = $product->category;
        $images = $product->images ?? [];
        $details = [
            'Назва' => $product->name,
            'Артикул' => $product->sku,
            'Категорія' => implode(' / ', array_filter([$category?->parent?->name, $category?->name])),
            'Ціна майстра' => $product->maker_price_minor !== null ? Money::display($product->maker_price_minor) : null,
            'Ціна в крамниці' => Money::display($product->price_minor),
            'Наявність' => $product->made_to_order ? 'Під замовлення' : $product->stock.' шт.',
            'Статус' => $product->is_active ? 'Опубліковано' : ($isMakerProduct ? 'На модерації' : 'Приховано'),
            $isMakerProduct ? 'Майстер' : 'Додав' => $author->name,
            'Електронна пошта' => $author->email,
            'Телефон' => $author->phone,
            'Фото' => $images === [] ? null : implode("\n", array_map(fn (string $image) => Storage::disk('public')->url($image), $images)),
            'Опис' => $description ?? html_entity_decode(strip_tags((string) $product->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
        $this->enqueue('product:'.$product->id.':'.$event, $heading, array_filter($details, filled(...)), '/admin/products');
    }

    public function sendTest(): bool
    {
        return $this->configured() && $this->send(
            "✅ <b>Рідна Віра · Telegram підключено</b>\n\nСюди надходитимуть замовлення, платежі, вироби на модерації та звернення з сайту."
        )?->json('ok') === true;
    }

    /** @return list<string>|null */
    public function privateChats(): ?array
    {
        if (! $this->hasBotToken()) {
            return null;
        }

        $response = $this->request('getUpdates', ['allowed_updates' => ['message'], 'limit' => 100]);
        if ($response?->json('ok') !== true) {
            return null;
        }

        $chats = [];
        foreach ($response->json('result', []) as $update) {
            $id = $this->privateChatId($update);
            if ($id !== null) {
                $chats[$id] = $id;
            }
        }

        return array_values($chats);
    }

    public function process(): int
    {
        if (! $this->configured()) {
            return 0;
        }

        return (int) Cache::lock('telegram:process-alerts', 180)->get(function (): int {
            $sent = $this->deliver();
            $this->answerCommands();

            return $sent;
        });
    }

    /** @return list<string> */
    private function format(string $heading, array $lines, ?string $adminPath): array
    {
        $parts = [];
        $body = [];
        $length = 0;
        foreach ($lines as $label => $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $separator = str_contains($value, "\n") || mb_strlen($value) > 120 ? ":\n" : ': ';
            $labelText = is_string($label) ? $label.$separator : '';
            foreach (mb_str_split($value, self::TEXT_LIMIT - 120) as $piece) {
                $lengthWithLine = mb_strlen($labelText.$piece) + 2;
                if ($length + $lengthWithLine > self::TEXT_LIMIT && $body !== []) {
                    $parts[] = implode("\n", $body);
                    $body = [];
                    $length = 0;
                }
                $body[] = ($labelText !== '' ? '<b>'.e($labelText).'</b>' : '').e($piece);
                $length += $lengthWithLine;
            }
        }
        $parts[] = implode("\n", $body);

        $link = $adminPath === null ? '' : "\n\n".'<a href="'.e(rtrim((string) config('app.url'), '/').$adminPath).'">Відкрити в адмінці →</a>';
        $total = count($parts);
        foreach ($parts as $index => &$part) {
            $page = $total > 1 ? ' · '.($index + 1).'/'.$total : '';
            $part = '<b>'.e($heading.$page).'</b>'.($part !== '' ? "\n\n".$part : '').$link;
        }

        return $parts;
    }

    /** @param  list<string>|null  $eventKeys */
    private function deliver(?array $eventKeys = null): int
    {
        $pending = DB::table('telegram_alerts')->whereNull('sent_at')->whereNull('failed_at');
        $due = (clone $pending)->where('available_at', '<=', now())->orderBy('id');
        $alerts = ($eventKeys === null ? $due->limit(10) : $due->whereIn('event_key', $eventKeys))->get();

        $sent = 0;
        foreach ($alerts as $alert) {
            $claimed = (clone $pending)->where('id', $alert->id)->where('available_at', '<=', now())
                ->update(['available_at' => now()->addSeconds(15)]);
            if ($claimed === 0) {
                continue;
            }

            $record = DB::table('telegram_alerts')->where('id', $alert->id);
            try {
                $message = Crypt::decryptString($alert->message);
            } catch (DecryptException) {
                $record->update(['failed_at' => now()]);
                Log::warning('Unable to decrypt a Telegram notification.', ['id' => $alert->id]);

                continue;
            }
            $response = $this->send($message);
            $attempts = $alert->attempts + 1;

            if ($response?->json('ok') === true) {
                $record->update(['sent_at' => now(), 'message' => null, 'attempts' => $attempts]);
                $sent++;

                continue;
            }

            $retryAfter = (int) $response?->json('parameters.retry_after');
            $retryAt = now()->addSeconds(max(min(3600, 60 * (2 ** ($attempts - 1))), min(86400, $retryAfter)));
            $record->update([
                'attempts' => $attempts,
                'available_at' => $retryAt,
                'failed_at' => $attempts >= 8 ? now() : null,
            ]);
            Log::warning('Telegram notification delivery failed.', [
                'id' => $alert->id, 'attempts' => $attempts, 'status' => $response?->status(),
            ]);

            if ($response?->status() === 429) {
                (clone $pending)->where('available_at', '<', $retryAt)->update(['available_at' => $retryAt]);

                break;
            }
        }

        return $sent;
    }

    private function answerCommands(): void
    {
        $offsetKey = 'telegram:offset:'.hash('sha256', (string) config('services.telegram.bot_token'));
        $response = $this->request('getUpdates', [
            'offset' => (int) Cache::get($offsetKey, 0),
            'allowed_updates' => ['message'],
            'limit' => 10,
        ]);
        if ($response?->json('ok') !== true) {
            return;
        }

        foreach ($response->json('result', []) as $update) {
            if ($this->privateChatId($update) === (string) config('services.telegram.chat_id')) {
                $command = explode(' ', trim((string) data_get($update, 'message.text')), 2)[0];
                $answer = match ($command) {
                    '/start', '/help' => "🔔 <b>Рідна Віра · Сповіщення сайту</b>\n\nЯ надсилаю нові замовлення, платежі, вироби та звернення.\n/status — перевірити підключення",
                    '/status' => '✅ <b>Бот підключено до сайту.</b>',
                    default => null,
                };
                if ($answer !== null && $this->send($answer)?->json('ok') !== true) {
                    break;
                }
            }

            Cache::put($offsetKey, (int) $update['update_id'] + 1, now()->addDays(2));
        }
    }

    private function privateChatId(array $update): ?string
    {
        if (data_get($update, 'message.chat.type') !== 'private') {
            return null;
        }
        $id = (string) data_get($update, 'message.chat.id');

        return preg_match('/^[1-9][0-9]*$/D', $id) === 1 ? $id : null;
    }

    private function send(string $message): ?Response
    {
        return $this->request('sendMessage', [
            'chat_id' => (string) config('services.telegram.chat_id'),
            'text' => $message,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'protect_content' => true,
        ]);
    }

    private function request(string $method, array $data): ?Response
    {
        $token = config('services.telegram.bot_token');
        try {
            return Http::acceptJson()->connectTimeout(3)->timeout(5)->withoutRedirecting()
                ->post("https://api.telegram.org/bot{$token}/{$method}", $data);
        } catch (ConnectionException) {
            return null;
        }
    }
}
