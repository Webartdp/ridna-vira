<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\ProductImages;
use App\Support\AboutGallery;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AboutPhotos extends Page
{
    protected static ?string $title = 'Світлини центру';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected string $view = 'filament.about-photos';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('content') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['photos' => AboutGallery::paths()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FileUpload::make('photos')->label('Галерея сторінки «Про центр»')
                ->helperText('Перетягуйте фото, щоб змінити порядок. Щоб замінити світлину, видаліть її з галереї та завантажте нову.')
                ->multiple()->reorderable()->image()->panelLayout('grid')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)->maxFiles(30)
                ->disk('public')->directory('content/about-gallery')->visibility('public')
                ->fetchFileInformation(false)
                ->preventFilePathTampering(true, fn (string $file) => in_array($file, AboutGallery::paths(), true))
                ->getUploadedFileUsing(function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                    if (! $component->isFilePathAuthorized($file)) {
                        return null;
                    }

                    $url = AboutGallery::defaultUrl($file);

                    return $url
                        ? ['name' => basename($file), 'size' => 0, 'type' => 'image/png', 'url' => $url]
                        : $component->getUploadedFile($file, $storedFileNames);
                }),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $previous = AboutGallery::paths();
        $photos = $this->form->getState()['photos'] ?? [];

        foreach (array_diff($photos, $previous) as $path) {
            app(ProductImages::class)->generate($path);
        }

        SiteSetting::write('about_gallery', json_encode(array_values($photos), JSON_THROW_ON_ERROR));
        Notification::make()->title('Світлини збережено')->success()->send();
    }
}
