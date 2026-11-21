<?php

namespace App\Console\Commands;

use App\Services\MakerTelegram;
use Illuminate\Console\Command;

class MakerTelegramCommand extends Command
{
    protected $signature = 'telegram:makers {--check : Перевірити токен та ім’я бота}';

    protected $description = 'Отримати команди бота майстрів і надіслати сповіщення про замовлення';

    public function handle(MakerTelegram $telegram): int
    {
        if (! $telegram->configured()) {
            $this->error('Вкажіть TELEGRAM_MAKER_BOT_TOKEN і TELEGRAM_MAKER_BOT_USERNAME окремого бота для майстрів.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            if (! $telegram->verifyConfiguration()) {
                $this->error('Не вдалося підтвердити бота в Telegram. Перевірте токен та ім’я бота.');

                return self::FAILURE;
            }

            $this->info('Telegram підтвердив токен та ім’я бота майстрів.');

            return self::SUCCESS;
        }

        $this->info('Надіслано сповіщень майстрам: '.$telegram->process());

        return self::SUCCESS;
    }
}
