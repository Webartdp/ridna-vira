<?php

namespace App\Filament\Blocks;

use App\Support\CmsHtml;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class ReadingPassage extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'reading-passage';
    }

    public static function getLabel(): string
    {
        return 'Молитва, славлення або обрядові дії';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            Select::make('kind')->label('Тип уривка')->options(self::labels())->default('prayer')->required(),
            Textarea::make('text')->label('Текст')->rows(10)->required()->maxLength(50000)->helperText('Переноси рядків зберігаються.'),
        ])->fillForm(fn (array $arguments) => $arguments['config'] ?? []);
    }

    public static function labels(): array
    {
        return ['prayer' => 'Молитва', 'praise' => 'Славлення', 'song' => 'Пісня', 'direction' => 'Обрядові дії', 'note' => 'Примітка', 'oath' => 'Присяга', 'spell' => 'Замовляння'];
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $kind = is_string($config['kind'] ?? null) && array_key_exists($config['kind'], self::labels()) ? $config['kind'] : 'prayer';

        $body = is_string($config['html'] ?? null) ? CmsHtml::clean($config['html']) : '<p>'.nl2br(e(is_string($config['text'] ?? null) ? $config['text'] : '')).'</p>';

        return '<div class="ritual-passage ritual-passage--'.$kind.'" role="group" aria-label="'.self::labels()[$kind].'"><p class="ritual-passage__label">'.self::labels()[$kind].'</p>'.$body.'</div>';
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return self::toHtml($config, []);
    }
}
