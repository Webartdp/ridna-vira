<?php

namespace App\Console\Commands;

use App\Services\TelegramAlerts;
use Illuminate\Console\Command;

class TelegramAlertsCommand extends Command
{
    protected $signature = 'telegram:alerts {--chats : Показати ID приватних чатів бота} {--test : Надіслати тестове сповіщення}';

    protected $description = 'Налаштувати або надіслати сповіщення сайту в Telegram';

    public function handle(TelegramAlerts $telegram): int
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
}
