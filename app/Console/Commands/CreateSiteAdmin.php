<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSiteAdmin extends Command
{
    protected $signature = 'site:admin';

    protected $description = 'Створити адміністратора сайту без стандартного пароля';

    public function handle(): int
    {
        $name = $this->ask('Ім’я адміністратора');
        if (is_string($name) && (! mb_check_encoding($name, 'UTF-8') || str_contains($name, "\u{FFFD}"))) {
            $this->error('Ім’я містить пошкоджені символи. Перевірте кодування термінала UTF-8 або введіть ім’я латиницею, наприклад Admin.');

            return self::FAILURE;
        }
        $data = ['name' => $name, 'email' => $this->ask('Електронна пошта'), 'password' => $this->secret('Пароль (мінімум 12 символів)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:150', 'email' => 'required|email|unique:users|max:190', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        if ($data['password'] !== $this->secret('Повторіть пароль')) {
            $this->error('Паролі не збігаються.');

            return self::FAILURE;
        }
        $user = User::create($data);
        $user->forceFill(['is_admin' => true, 'role' => 'admin', 'is_active' => true])->save();
        $this->info('Адміністратора створено. Вхід: '.url('/admin'));

        return self::SUCCESS;
    }
}
