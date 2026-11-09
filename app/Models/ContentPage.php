<?php

namespace App\Models;

use App\Support\CmsHtml;
use App\Support\EditableLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPage extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'is_imported'];

    private ?array $pendingLayoutFields = null;

    public function setLayoutFieldsAttribute(?array $fields): void
    {
        $this->pendingLayoutFields = $fields;
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_published' => 'boolean', 'is_override' => 'boolean', 'is_imported' => 'boolean'];
    }

    public static function sections(): array
    {
        return ['pages' => 'Основні сторінки', 'news' => 'Новини', 'articles' => 'Статті', 'creativity' => 'Творчість', 'gods' => 'Боги', 'holidays' => 'Свята', 'shrines' => 'Святині', 'rituals' => 'Обряди', 'prayers' => 'Молитви та слави', 'books' => 'Книги', 'documents' => 'Документи'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $page) {
            if ($page->isDirty()) {
                $page->revisions()->create(['user_id' => auth()->id(), 'snapshot' => json_encode($page->getRawOriginal(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
        });
        static::saving(function (self $page) {
            if (! app()->bound('cms.importing')) {
                if ($page->editor_mode === 'layout' && $page->pendingLayoutFields !== null) {
                    $page->body = EditableLayout::apply($page->body ?? '', $page->pendingLayoutFields);
                    $page->pendingLayoutFields = null;
                }
                $page->is_override = true;
                $page->body = CmsHtml::clean($page->body ?? '', $page->editor_mode === 'layout');
            }
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class);
    }

    public function visible(): bool
    {
        return ! $this->trashed() && $this->is_published && (! $this->published_at || $this->published_at->isPast());
    }

    public function scopePublicOverrides(Builder $query): Builder
    {
        return $query->withTrashed()
            ->select(['id', 'path', 'section', 'category', 'title', 'excerpt', 'image', 'video_url', 'published_at', 'created_at', 'is_published', 'is_override', 'deleted_at'])
            ->where(fn ($query) => $query->where('is_override', true)->orWhere('is_published', false)->orWhereNotNull('deleted_at')->orWhere('published_at', '>', now()));
    }
}
