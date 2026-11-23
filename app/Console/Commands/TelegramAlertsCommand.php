<?php

namespace App\Console\Commands;

use App\Services\AdminTelegramWebhook;
use App\Services\TelegramAlerts;
use Illuminate\Console\Command;

class TelegramAlertsCommand extends Command
{
    protected $signature = 'telegram:alerts
        {--chats : Показати ID приватних чатів бота}
        {--test : Надіслати тестове сповіщення}
        {--webhook : Підключити пряме приймання дій з Telegram}
        {--polling : Повернути опитування Telegram через планувальник}
        {--check : Перевірити поточний режим приймання дій}';

    protected $description = 'Налаштувати або надіслати сповіщення сайту в Telegram';

    public function handle(TelegramAlerts $telegram, AdminTelegramWebhook $webhook): int
    {
        if ($this->option('chats')) {
            $chats = $telegram->privateChats();
            if ($chats === null) {
                $this->error('Не вдалося отримати чати. Перевірте TELEGRAM_BOT_TOKEN та чи бот працює без webhook.');

                return self::FAILURE;
            }
            if ($chats === []) {
                $this->line('Відкрийте свого бота в Telegram, натисніть Start і повторіть команду.');
            }
            foreach ($chats as $id) {
                $this->line($id);
            }

            return self::SUCCESS;
        }

        if (! $telegram->configured()) {
            $this->error('Вкажіть TELEGRAM_BOT_TOKEN і TELEGRAM_CHAT_ID приватного чату в .env.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            return $this->checkWebhook($webhook);
        }

        if ($this->option('webhook') || $this->option('polling')) {
            $install = $this->option('webhook');
            $error = $install ? $webhook->install() : $webhook->switchToPolling();
            if ($error !== null) {
                $this->error($error);

                return self::FAILURE;
            }
            $this->info($install ? 'Webhook адміністратора підключено.' : 'Бот прийматиме дії через планувальник.');

            return self::SUCCESS;
        }

        if ($this->option('test')) {
            if (! $telegram->sendTest()) {
                $this->error('Telegram не прийняв сповіщення. Перевірте токен, ID чату й чи натиснуто Start у боті.');

                return self::FAILURE;
            }

            $this->info('Тестове сповіщення надіслано.');

            return self::SUCCESS;
        }

        $this->info('Надіслано сповіщень: '.$telegram->process());

        return self::SUCCESS;
    }

    private function checkWebhook(AdminTelegramWebhook $webhook): int
    {
        $status = $webhook->status();
        if ($status === null) {
            $this->error('Не вдалося отримати стан бота від Telegram.');

            return self::FAILURE;
        }

        $this->line(match ($status['mode']) {
            'polling' => 'Бот приймає дії через опитування планувальником.',
            'webhook' => 'Webhook адміністратора підключено.',
            'external' => 'У Telegram зареєстровано webhook для іншої адреси.',
            default => 'Налаштування сайту й Telegram різняться. Повторіть telegram:alerts --webhook.',
        });
        $this->line('Дій у черзі Telegram — '.$status['pending'].'.');
        if ($status['error_at'] > 0) {
            $this->warn('Telegram зафіксував помилку доставки на webhook.');
        }

        return in_array($status['mode'], ['polling', 'webhook'], true) ? self::SUCCESS : self::FAILURE;
    }
}
