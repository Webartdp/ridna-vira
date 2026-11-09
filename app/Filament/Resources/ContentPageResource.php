<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\ReadingPassage;
use App\Models\ContentPage;
use App\Support\CmsHtml;
use App\Support\EditableLayout;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;

class ContentPageResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = ContentPage::class;

    protected static ?string $modelLabel = 'сторінку';

    protected static ?string $pluralModelLabel = 'Сторінки та матеріали';

    protected static ?string $navigationLabel = 'Увесь сайт';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull(),
            Select::make('section')->label('Розділ')->options(ContentPage::sections())->required()->default('pages')->live(),
            Select::make('category')->label('Група обрядів / молитов')->options(['life' => 'Обряди життєвого кола', 'calendar' => 'Обряди Кола Сварожого', 'school' => 'Обряди Недільної школи', 'literature' => 'Література про обряди', 'themes' => 'Молитви за темами', 'authors' => 'Авторські молитви', 'books' => 'Молитовник', 'research' => 'Дослідження'])->placeholder('Залишити наявну групу')->visible(fn (Get $get) => in_array($get('section'), ['rituals', 'prayers'], true)),
            TextInput::make('category')->label('Категорія')->maxLength(190)->visible(fn (Get $get) => ! in_array($get('section'), ['rituals', 'prayers'], true)),
            TextInput::make('path')->label('Адреса на сайті')->placeholder('/novyny/nazva-novyny')->required()->unique(ignoreRecord: true)
                ->maxLength(255)->regex('~^/(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?$~D')->disabled(fn (?ContentPage $record) => (bool) $record?->is_imported)
                ->rules([fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    if (preg_match('~^/(?:admin|livewire|assets|storage|build|up|kramnychka|koshyk|zamovlennia|oplata)(?:/|$)~', $value)) {
                        $fail('Цю адресу зарезервовано для системи та крамниці.');
                    }
                    $prefix = ['news' => '/novyny/', 'articles' => '/statti/', 'creativity' => '/tvorchist/', 'gods' => '/ridna-vira/bohy/', 'holidays' => '/ridna-vira/kalendar/', 'shrines' => '/ridna-vira/sviatyni/', 'rituals' => '/ridna-vira/obriady/', 'prayers' => '/ridna-vira/molytvy/', 'documents' => '/dokumenti/'][$get('section')] ?? null;
                    if ($prefix && ! preg_match('~^'.preg_quote($prefix, '~').'[a-z0-9-]+$~D', $value)) {
                        $fail('Для цього розділу адреса має вигляд '.$prefix.'nazva-materialu');
                    }
                }])->helperText('Латинські літери, цифри й дефіси. Імпортовані адреси збережено.'),
            Textarea::make('excerpt')->label('Короткий опис / опис для пошуку')->rows(3)->maxLength(1000)->columnSpanFull(),
            Select::make('editor_mode')->label('Редактор')->options(['reading' => 'Новий матеріал', 'fragment' => 'Текст у наявному оформленні', 'layout' => 'Повна сторінка (HTML)'])->default('reading')->required()->live()
                ->helperText('Для головної й сторінок зі складною версткою збережіть режим HTML. Позначки [[live:…]] виводять актуальні форми, мапу та стрічки.'),
            Toggle::make('source_editor')->label('Редагувати HTML тексту')->default(false)->live()->dehydrated(false),
            Toggle::make('is_published')->label('Опубліковано')->default(true),
            DateTimePicker::make('published_at')->label('Дата публікації (Київ)')->timezone('Europe/Kyiv')->seconds(false)->helperText('Майбутня дата запланує публікацію.'),
            TextInput::make('video_url')->label('Відео YouTube')->url()->maxLength(255)->regex('~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)[A-Za-z0-9_-]{11}(?:[?&#/].*)?$~D'),
            FileUpload::make('image')->label('Обкладинка')->disk('public')->directory('content')->visibility('public')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096),
            Repeater::make('layout_fields')->label('Тексти, зображення та посилання сторінки')->columnSpanFull()->collapsed()->addable(false)->deletable(false)->reorderable(false)
                ->visible(fn (Get $get) => $get('editor_mode') === 'layout' && ! $get('source_editor'))
                ->afterStateHydrated(fn ($component, Get $get) => $component->state(EditableLayout::fields($get('body') ?? '')))
                ->itemLabel(fn (array $state) => $state['label'] ?? 'Текст')->schema([
                    Hidden::make('key'), Hidden::make('type'), Hidden::make('label'),
                    Textarea::make('value')->label('Вміст')->rows(3)->maxLength(50000),
                ])->helperText('Змінюйте вміст блоків без редагування коду. Для нового зображення завантажте його в «Файли та видання» й вставте адресу.'),
            RichEditor::make('body')->label('Матеріал')->visible(fn (Get $get) => $get('editor_mode') !== 'layout' && ! $get('source_editor'))->columnSpanFull()
                ->formatStateUsing(fn ($state, Get $get) => $get('editor_mode') === 'layout' ? $state : CmsHtml::forEditor($state ?? ''))
                ->customBlocks([ReadingPassage::class])
                ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('content')->fileAttachmentsVisibility('public')->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->fileAttachmentsMaxSize(4096)
                ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'link'], ['h2', 'h3', 'blockquote', 'bulletList', 'orderedList'], ['attachFiles', 'table', 'customBlocks'], ['undo', 'redo']])
                ->helperText('Заголовки ділять текст на розділи. Цитатою можна виділити молитву або славу. Для збереження складних обрядових позначень доступний HTML-редактор.'),
            Textarea::make('body')->label('HTML сторінки')->visible(fn (Get $get) => (bool) $get('source_editor'))->rows(26)->columnSpanFull()
                ->helperText('Редагуйте текст, зображення та посилання. Не видаляйте [[live:…]], якщо потрібні жива стрічка, карта чи форма.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->label('Назва')->searchable()->sortable()->wrap(),
            TextColumn::make('section')->label('Розділ')->formatStateUsing(fn ($state) => ContentPage::sections()[$state] ?? $state)->badge(),
            TextColumn::make('path')->label('Адреса')->searchable()->toggleable(),
            IconColumn::make('is_published')->label('Опубліковано')->boolean(),
            IconColumn::make('is_override')->label('Відредаговано')->boolean(),
            TextColumn::make('updated_at')->label('Оновлено')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([SelectFilter::make('section')->label('Розділ')->options(ContentPage::sections()), TrashedFilter::make()->label('Кошик')])
            ->recordActions([
                EditAction::make()->modalWidth('7xl'),
                Action::make('preview')->label('На сайт')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (ContentPage $record) => url($record->path))->openUrlInNewTab(),
                Action::make('revision')->label('Попередня версія')->schema([
                    Select::make('revision')->label('Версія')->options(fn (ContentPage $record) => $record->revisions()->latest('id')->limit(30)->get()->mapWithKeys(fn ($revision) => [$revision->id => $revision->created_at->format('d.m.Y H:i:s').' · #'.$revision->id]))->required(),
                ])->action(function (ContentPage $record, array $data) {
                    $revision = $record->revisions()->findOrFail($data['revision']);
                    $snapshot = json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $record->fill(Arr::only($snapshot, ['title', 'body', 'excerpt', 'image', 'video_url', 'editor_mode', 'is_published', 'published_at']))->save();
                })->visible(fn (ContentPage $record) => $record->revisions()->exists()),
                Action::make('original')->label('Початковий вигляд')->requiresConfirmation()->visible(fn (ContentPage $record) => $record->is_imported && $record->is_override)->action(fn (ContentPage $record) => $record->forceFill(['is_override' => false])->saveQuietly()),
                DeleteAction::make(), RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return ['index' => ContentPageResource\Pages\ManageContentPages::route('/')];
    }
}
