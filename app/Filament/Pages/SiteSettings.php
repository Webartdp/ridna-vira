<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\Site;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class SiteSettings extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    protected static ?string $title = 'Налаштування сайту й оплати';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.settings';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill([
            'announcement' => SiteSetting::read('announcement', 'Духовний центр «Рідна Віра» — жива традиція українців'),
            'footer_text' => SiteSetting::read('footer_text', 'РІДНА ВІРА — Всі права захищені'),
            'extra_links' => json_decode(SiteSetting::read('extra_links', '[]'), true),
            'navigation' => Site::navigation(),
            'social_links' => json_decode(SiteSetting::read('social_links', '{}'), true),
            'logo' => SiteSetting::read('logo'),
            'partners' => json_decode(SiteSetting::read('partners', json_encode(config('site_partners'), JSON_UNESCAPED_UNICODE)), true),
            'contact_email' => SiteSetting::read('contact_email', 'help@jaromir.com.ua'),
            'order_email' => SiteSetting::read('order_email'),
            'shop_enabled' => filter_var(SiteSetting::read('shop_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'wayforpay_account' => SiteSetting::read('wayforpay_account', config('shop.wayforpay_account')),
            'wayforpay_domain' => SiteSetting::read('wayforpay_domain', config('shop.wayforpay_domain')),
            'wayforpay_secret' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('announcement')->label('Рядок оголошення')->maxLength(500),
            Textarea::make('footer_text')->label('Текст у підвалі')->maxLength(1000),
            Section::make('Соціальні мережі')->description('Посилання відображаються в підвалі сайту. Залиште поле порожнім, щоб приховати мережу.')->schema(
                collect(Site::socialNetworks())->map(fn ($label, $network) => TextInput::make('social_links.'.$network)->label($label)->url()->regex('~^https://~')->maxLength(500)->placeholder('https://'))->values()->all()
            )->columns(2),
            TextInput::make('contact_email')->label('Пошта для звернень із контактної форми')->email()->required()->maxLength(190),
            TextInput::make('order_email')->label('Пошта для сповіщень про оплачені замовлення')->email()->maxLength(190),
            Toggle::make('shop_enabled')->label('Дозволити оформлення замовлень')->helperText('Для оплати також потрібні налаштовані реквізити WayForPay.'),
            Repeater::make('partners')->label('Ресурси в підвалі')->collapsed()->itemLabel(fn (array $state) => $state['name'] ?? 'Ресурс')->maxItems(12)->schema([
                TextInput::make('name')->label('Назва')->required()->maxLength(190), TextInput::make('url')->label('Посилання')->required()->regex('~^(https://|/(?!/))~')->maxLength(255),
                TextInput::make('image')->label('Адреса зображення')->required()->regex('~^(https://|/(?!/))~')->maxLength(500),
                Select::make('style')->label('Розмір логотипа')->options(['polumya' => 'Широкий', 'ridna' => 'Основний', 'svarga' => 'Компактний'])->default('ridna')->required(),
            ]),
            FileUpload::make('logo')->label('Логотип')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('site')->visibility('public')->maxSize(2048),
            Repeater::make('navigation')->label('Головне меню та меню в підвалі')->collapsed()->itemLabel(fn (array $state) => $state['label'] ?? 'Посилання')->maxItems(15)->schema([
                TextInput::make('label')->label('Назва')->required()->maxLength(100),
                TextInput::make('url')->label('Адреса')->required()->maxLength(255)->regex('~^(https://|/(?!/))~'),
                Repeater::make('children')->label('Підменю')->maxItems(20)->schema([
                    TextInput::make('label')->label('Назва')->required()->maxLength(100), TextInput::make('url')->label('Адреса')->required()->maxLength(255)->regex('~^(https://|/(?!/))~'),
                ])->columns(2)->defaultItems(0),
            ]),
            Repeater::make('extra_links')->label('Додаткові посилання в меню та підвалі')->schema([
                TextInput::make('label')->label('Назва')->required()->maxLength(100),
                TextInput::make('url')->label('Адреса')->required()->maxLength(255)->regex('~^(https://|/(?!/))~'),
            ])->columns(2)->defaultItems(0)->maxItems(12),
            TextInput::make('wayforpay_account')->label('WayForPay · Merchant Account')->maxLength(190)->regex('/^[a-zA-Z0-9_-]*$/D'),
            TextInput::make('wayforpay_domain')->label('WayForPay · домен магазину')->required()->maxLength(190)->regex('/^(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/D'),
            TextInput::make('wayforpay_secret')->label('WayForPay · Secret Key')->password()->revealable()->autocomplete('new-password')->maxLength(500)
                ->helperText('Залиште порожнім, щоб зберегти поточний ключ. Ключ зберігається зашифрованим і не повертається до форми.'),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                if ($key === 'wayforpay_secret' && blank($value)) {
                    continue;
                }
                SiteSetting::write($key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $value);
            }
        });
        $this->data['wayforpay_secret'] = '';
        Notification::make()->title('Налаштування збережено')->success()->send();
    }
}
