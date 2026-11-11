<?php

namespace App\Filament\Resources;

use App\Models\ContentPage;
use App\Support\Cms;
use App\Support\ContentImages;
use App\Support\Seo;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArticleResource extends ContentPageResource
{
    public static function table(Table $table): Table
    {
        $table = parent::table($table);
        $actions = collect($table->getRecordActions());
        $edit = $actions->first(fn ($action) => $action->getName() === 'edit');

        return $table->columns([
            ImageColumn::make('image')->label('')->getStateUsing(fn (ContentPage $record) => $record->image ? ContentImages::visual($record->image)['src'] : null)->defaultImageUrl(asset('icons/icon-192.png'))->imageSize(52)->square(),
            TextColumn::make('title')->label('Стаття')->searchable()->sortable()->weight('semibold')->wrap()->lineClamp(2)->description(fn (ContentPage $record) => static::summary($record)),
            TextColumn::make('publication_state')->label('Статус')->getStateUsing(fn (ContentPage $record) => $record->trashed() ? 'У кошику' : (! $record->is_published ? 'Чернетка' : ($record->published_at?->isFuture() ? 'Заплановано' : 'Опубліковано')))->badge()->color(fn ($state) => match ($state) {
                'Опубліковано' => 'success', 'Заплановано' => 'warning', 'У кошику' => 'danger', default => 'gray'
            }),
            TextColumn::make('updated_at')->label('Змінено')->date('d.m.Y')->tooltip(fn (ContentPage $record) => $record->updated_at?->format('d.m.Y H:i'))->sortable()->toggleable(),
            TextColumn::make('path')->label('Адреса')->searchable()->wrap()->toggleable(isToggledHiddenByDefault: true),
        ])->recordActions([
            $edit->label('Редагувати')->button()->color('gray'),
            ActionGroup::make($actions->reject(fn ($action) => $action->getName() === 'edit')->all())->label('Інші дії')->icon('heroicon-m-ellipsis-horizontal'),
        ])->paginated([10, 25, 50])->defaultPaginationPageOption(25)->emptyStateHeading('Статей поки немає')->emptyStateDescription('Додайте матеріал і підготуйте його до публікації.');
    }

    private static function summary(ContentPage $record): string
    {
        $text = $record->excerpt ?? '';
        if (! $record->is_override && $record->body) {
            $body = $record->editor_mode === 'layout' ? (Cms::fragment($record->body) ?? '') : $record->body;
            if ($body !== '') {
                $text = preg_replace('~</(?:h[1-6]|div|p|li|tr)>~i', ' ', $body);
            }
        }

        return Seo::text($text, 110);
    }

    protected static ?string $slug = 'articles';

    protected static ?string $modelLabel = 'статтю';

    protected static ?string $pluralModelLabel = 'Статті';

    protected static ?string $navigationLabel = 'Статті';

    protected static string|\UnitEnum|null $navigationGroup = 'Публікації';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    protected static string $contentSection = 'articles';

    protected static string $createLabel = 'Додати статтю';

    public static function getPages(): array
    {
        return [
            'index' => ArticleResource\Pages\ManageArticleEntries::route('/'),
            'edit' => ArticleResource\Pages\EditArticleEntry::route('/{record}/edit'),
        ];
    }
}
