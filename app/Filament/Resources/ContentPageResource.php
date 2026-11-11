<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\ReadingPassage;
use App\Models\ContentPage;
use App\Support\CmsHtml;
use App\Support\ContentImages;
use App\Support\PageBlocks;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ContentPageResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = ContentPage::class;

    protected static ?string $modelLabel = 'сторінку';

    protected static ?string $pluralModelLabel = 'Сторінки';

    protected static ?string $navigationLabel = 'Сторінки';

    protected static string $contentSection = 'pages';

    protected static string $createLabel = 'Додати сторінку';

    public static function getCreateLabel(): string
    {
        return static::$createLabel;
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Hidden::make('section')->default(static::$contentSection)->required()->in([static::$contentSection])->dehydrateStateUsing(fn () => static::$contentSection),
            Hidden::make('editor_mode')->default('reading')->required()->in(['reading', 'fragment', 'layout']),
            Hidden::make('image_origin'),
            Tabs::make('Редагування матеріалу')->columnSpanFull()->tabs([
                Tab::make('Матеріал')->schema([
                    TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull()->live(onBlur: true)->afterStateUpdated(function ($state, Set $set, Get $get, ?ContentPage $record) {
                        if (! $record && blank($get('path')) && filled($state)) {
                            $prefix = ['books' => '/ridna-vira/knyhy/', 'communities' => '/hromady/', 'news' => '/novyny/', 'articles' => '/statti/', 'creativity' => '/tvorchist/', 'gods' => '/ridna-vira/bohy/', 'holidays' => '/ridna-vira/kalendar/', 'shrines' => '/ridna-vira/sviatyni/', 'rituals' => '/ridna-vira/obriady/', 'prayers' => '/ridna-vira/molytvy/', 'documents' => '/dokumenti/'][static::$contentSection] ?? '/';
                            $set('path', $prefix.Str::slug($state));
                        }
                    }),
                    Textarea::make('excerpt')->label('Короткий опис')->rows(3)->maxLength(1000)->columnSpanFull(),
                    FileUpload::make('image')->label('Зображення')->disk('public')->directory('content')->visibility('public')->image()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->imageResizeUpscale(false)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)
                        ->preventFilePathTampering(true, fn (string $file, ?ContentPage $record) => $record && $file === (ContentImages::prepare($record->attributesToArray())['image'] ?? null))
                        ->helperText('Головне фото для сторінки, картки матеріалу та поширення в соцмережах.'),
                    RichEditor::make('body')->label('Основний текст')->visible(fn (Get $get) => $get('editor_mode') !== 'layout' && ! $get('source_editor'))->columnSpanFull()
                        ->minHeight('22rem')->maxHeight('32rem')
                        ->formatStateUsing(fn ($state, Get $get) => $get('editor_mode') === 'layout' ? $state : CmsHtml::forEditor($state ?? ''))
                        ->customBlocks([ReadingPassage::class])
                        ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('content')->fileAttachmentsVisibility('public')->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->fileAttachmentsMaxSize(4096)
                        ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'link'], ['h2', 'h3', 'blockquote', 'bulletList', 'orderedList'], ['attachFiles', 'table', 'customBlocks'], ['undo', 'redo']])
                        ->helperText('Форматуйте абзаци, заголовки й списки. Для молитов, славлень та обрядових дій використовуйте окремий блок на панелі редактора.'),
                    Repeater::make('page_blocks')->label('Блоки сторінки')->columnSpanFull()->collapsed()->addable(false)->deletable(false)->reorderable(false)
                        ->visible(fn (Get $get) => static::$contentSection === 'pages' && $get('editor_mode') === 'layout' && ! $get('source_editor'))
                        ->afterStateHydrated(fn ($component, Get $get) => $component->state(static::$contentSection === 'pages' && $get('editor_mode') === 'layout' ? PageBlocks::fields($get('body') ?? '') : []))
                        ->itemLabel(fn (array $state) => $state['label'] ?? 'Текстовий блок')->schema([
                            Hidden::make('key'), Hidden::make('kind'), Hidden::make('label'),
                            RichEditor::make('value')->label('Текст блоку')->minHeight('12rem')->maxHeight('24rem')->visible(fn (Get $get) => in_array($get('kind'), ['rich', 'paragraph']))->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3', 'bulletList', 'orderedList'], ['undo', 'redo']]),
                            Image::make(fn (Get $get) => preg_match('~^(https?://|/(?!/))~i', $get('value') ?? '') ? $get('value') : '', 'Поточне зображення')->imageHeight('140px')->visible(fn (Get $get) => $get('kind') === 'image'),
                            TextInput::make('value')->label(fn (Get $get) => match ($get('kind')) {
                                'image' => 'Адреса зображення', 'link' => 'Посилання', default => 'Заголовок'
                            })->visible(fn (Get $get) => ! in_array($get('kind'), ['rich', 'paragraph']))->maxLength(2000),
                            FileUpload::make('replacement')->label('Замінити зображення')->visible(fn (Get $get) => $get('kind') === 'image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('pages')->visibility('public')->maxSize(4096),
                        ]),
                ]),
                Tab::make('Медіа')->schema([

                    TextInput::make('video_url')->label('Відео YouTube')->url()->maxLength(255)->regex('~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)[A-Za-z0-9_-]{11}(?:[?&#/].*)?$~D'),
                ]),
                Tab::make('Публікація')->schema([
                    Toggle::make('is_published')->label('Опубліковано')->default(true),
                    DateTimePicker::make('published_at')->label('Дата публікації (Київ)')->timezone('Europe/Kyiv')->seconds(false)->helperText('Майбутня дата запланує публікацію.'),
                    Select::make('category')->label('Група обрядів / молитов')->options(['life' => 'Обряди життєвого кола', 'calendar' => 'Обряди Кола Сварожого', 'school' => 'Обряди Недільної школи', 'literature' => 'Література про обряди', 'themes' => 'Молитви за темами', 'authors' => 'Авторські молитви', 'books' => 'Молитовник', 'research' => 'Дослідження'])->placeholder('Залишити наявну групу')->visible(fn (Get $get) => in_array($get('section'), ['rituals', 'prayers'], true)),
                    TextInput::make('category')->label('Категорія')->maxLength(190)->visible(fn (Get $get) => ! in_array($get('section'), ['rituals', 'prayers'], true)),
                ]),
                ...static::extraTabs(),
                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')->label('Заголовок у пошуку')->maxLength(255)->helperText('Якщо порожньо — використовується назва матеріалу.'),
                    Textarea::make('seo_description')->label('Опис у пошуку')->rows(3)->maxLength(500)->helperText('Коротко опишіть зміст сторінки. Якщо порожньо — опис формується автоматично.'),
                ]),
                Tab::make('Налаштування')->schema([
                    TextInput::make('path')->label('Адреса на сайті')->placeholder('/novyny/nazva-novyny')->required()->unique(ignoreRecord: true)
                        ->maxLength(255)->regex('~^/(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?$~D')->disabled(fn (?ContentPage $record) => (bool) $record?->is_imported)
                        ->rules([fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            if (preg_match('~^/(?:kabinet|uviyty|vyity|reiestratsiia|parol|mcp|admin|livewire|assets|storage|build|up|kramnychka|koshyk|zamovlennia|oplata)(?:/|$)~', $value)) {
                                $fail('Цю адресу зарезервовано для системи та крамниці.');
                            }
                            $prefix = ['books' => '/ridna-vira/knyhy/', 'communities' => '/hromady/', 'news' => '/novyny/', 'articles' => '/statti/', 'creativity' => '/tvorchist/', 'gods' => '/ridna-vira/bohy/', 'holidays' => '/ridna-vira/kalendar/', 'shrines' => '/ridna-vira/sviatyni/', 'rituals' => '/ridna-vira/obriady/', 'prayers' => '/ridna-vira/molytvy/', 'documents' => '/dokumenti/'][$get('section')] ?? null;
                            if ($prefix && ! preg_match('~^'.preg_quote($prefix, '~').'[a-z0-9-]+$~D', $value)) {
                                $fail('Для цього розділу адреса має вигляд '.$prefix.'nazva-materialu');
                            }
                        }])->helperText('Латинські літери, цифри й дефіси. Імпортовані адреси збережено.'),
                    Toggle::make('source_editor')->label('Редагувати HTML тексту')->default(false)->live()->dehydrated(false),
                    Textarea::make('body')->label('HTML сторінки')->visible(fn (Get $get) => (bool) $get('source_editor'))->rows(26)->columnSpanFull()
                        ->helperText('Редагуйте текст, зображення та посилання. Не видаляйте [[live:…]], якщо потрібні жива стрічка, карта чи форма.'),

                ]),
            ]),
        ]);
    }

    protected static function extraTabs(): array
    {
        return [];
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->label('Назва')->searchable()->sortable()->wrap(),
            TextColumn::make('path')->label('Адреса')->searchable()->toggleable(),
            IconColumn::make('is_published')->label('Опубліковано')->boolean(),
            IconColumn::make('is_override')->label('Відредаговано')->boolean(),
            TextColumn::make('updated_at')->label('Оновлено')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([TrashedFilter::make()->label('Кошик')])
            ->recordActions([
                EditAction::make()->label('Редагувати')->url(fn (ContentPage $record) => static::getUrl('edit', ['record' => $record])),
                Action::make('preview')->label('На сайт')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (ContentPage $record) => url($record->path))->openUrlInNewTab(),
                Action::make('revision')->label('Попередня версія')->authorize(fn () => auth()->user()?->canManage('content'))->schema([
                    Select::make('revision')->label('Версія')->options(fn (ContentPage $record) => $record->revisions()->latest('id')->limit(30)->get()->mapWithKeys(fn ($revision) => [$revision->id => $revision->created_at->format('d.m.Y H:i:s').' · #'.$revision->id]))->required(),
                ])->action(function (ContentPage $record, array $data) {
                    $revision = $record->revisions()->findOrFail($data['revision']);
                    $snapshot = json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $record->fill(Arr::only($snapshot, ['title', 'body', 'excerpt', 'image', 'image_origin', 'video_url', 'editor_mode', 'is_published', 'published_at', 'seo_title', 'seo_description', 'community', 'book']))->save();
                })->visible(fn (ContentPage $record) => $record->revisions()->exists()),
                Action::make('original')->label('Початковий вигляд')->authorize(fn () => auth()->user()?->canManage('content'))->requiresConfirmation()->visible(fn (ContentPage $record) => $record->is_imported && $record->is_override)->action(fn (ContentPage $record) => $record->forceFill(['is_override' => false])->saveQuietly()),
                DeleteAction::make(), RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->where('section', static::$contentSection);
    }

    public static function getPages(): array
    {
        return [
            'index' => ContentPageResource\Pages\ManageContentPages::route('/'),
            'edit' => ContentPageResource\Pages\EditContentPage::route('/{record}/edit'),
        ];
    }
}
