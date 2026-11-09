<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RestoreSiteAdminAccess extends Command
{
    protected $signature = 'site:admin-reset';

    protected $description = 'Відновити доступ адміністратора через SSH або створити його, якщо облікового запису немає';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->ask('Електронна пошта адміністратора')));
        if (! Validator::make(['email' => $email], ['email' => 'required|email|max:190'])->passes()) {
            $this->error('Вкажіть коректну електронну пошту.');

            return self::FAILURE;
        }
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $this->info('Облікового запису немає. Буде створено адміністратора з іменем Admin.');
        }
        $password = $this->secret('Новий пароль (від 12 символів, латиниця та цифри)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'regex:/\A[\x21-\x7E]{12,128}\z/']],
            ['password.regex' => 'Для введення через SSH використайте латинські літери, цифри й звичайні символи без пробілів.']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        if ($password !== $this->secret('Повторіть новий пароль')) {
            $this->error('Паролі не збігаються. Доступ не змінено.');

            return self::FAILURE;
        }
        $user ??= new User(['name' => 'Admin', 'email' => $email]);
        $user->forceFill(['password' => $password, 'is_admin' => true, 'remember_token' => Str::random(60)])->save();
        $this->info('Доступ адміністратора відновлено. Пошта для входу: '.$user->email);
        $this->info('Вхід: '.url('/admin'));

        return self::SUCCESS;
    }
}
