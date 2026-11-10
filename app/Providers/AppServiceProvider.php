<?php

namespace App\Providers;

use App\Models\ContentPage;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\ContentPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ShopPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([ContentPage::class, MediaAsset::class] as $model) {
            Gate::policy($model, ContentPolicy::class);
        }
        foreach ([Product::class, ProductCategory::class] as $model) {
            Gate::policy($model, ShopPolicy::class);
        }
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        ResetPassword::createUrlUsing(fn ($user, string $token) => route('password.reset', ['token' => $token, 'email' => $user->email]));
        VerifyEmail::toMailUsing(fn ($user, string $url) => (new MailMessage)
            ->subject('Підтвердження пошти — Рідна Віра')->greeting('Вітаємо!')
            ->line('Підтвердьте електронну пошту свого облікового запису на сайті «Рідна Віра».')
            ->action('Підтвердити пошту', $url)->line('Якщо ви не створювали обліковий запис, цей лист можна проігнорувати.')
            ->salutation('Духовний центр «Рідна Віра»'));
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Відновлення пароля — Рідна Віра')->greeting('Вітаємо!')
            ->line('Ми отримали запит на зміну пароля вашого особистого кабінету.')
            ->action('Створити новий пароль', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('Посилання діє '.config('auth.passwords.users.expire', 60).' хвилин.')
            ->line('Якщо ви не надсилали запит, ваш пароль залишається без змін.')
            ->salutation('Духовний центр «Рідна Віра»'));
    }
}
