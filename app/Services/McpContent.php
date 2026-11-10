<?php

namespace App\Services;

use App\Models\ContentPage;
use App\Support\Cms;
use App\Support\ContentEditor;
use App\Support\ContentImages;
use App\Support\PageBlocks;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McpContent
{
    public static function version(ContentPage $page): string
    {
        return hash('sha256', json_encode($page->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    public function describe(ContentPage $page): array
    {
        $layout = $page->section === 'pages' && $page->editor_mode === 'layout';

        return [
            ...Arr::only($page->toArray(), ['id', 'path', 'section', 'title', 'excerpt', 'seo_title', 'seo_description', 'video_url', 'image', 'is_published', 'published_at', 'updated_at']),
            'version' => self::version($page), 'visible' => $page->visible(),
            'body_html' => $layout ? null : ($page->editor_mode === 'layout' ? (Cms::fragment($page->body ?? '') ?? $page->body) : $page->body),
            'blocks' => $layout ? PageBlocks::fields($page->body ?? '') : [],
            'image_url' => $page->image ? ContentImages::visual($page->image)['src'] : null,
            'editing' => $layout ? 'Use blocks with existing keys; header, footer and live forms are preserved.' : 'Use body_html for the reading text; use set-content-image for the primary photo.',
        ];
    }

    public function listing(array $input): array
    {
        $query = ContentPage::query();
        if (! empty($input['section'])) {
            $query->where('section', $input['section']);
        }
        if (! empty($input['search'])) {
            $search = $input['search'];
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('path', 'like', '%'.$search.'%'));
        }
        $page = $query->orderBy('id')->paginate($input['limit'] ?? 25, ['id', 'path', 'section', 'title', 'is_published', 'published_at', 'updated_at'], 'page', $input['page'] ?? 1);

        return ['sections' => ContentPage::sections(), 'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'items' => $page->items()];
    }

    public function get(int $id): array
    {
        return $this->describe(ContentPage::findOrFail($id));
    }

    public function update(array $input): array
    {
        return $this->mutate($input, 'update', function (ContentPage $page) use ($input) {
            $page->fill(Arr::only($input, ['title', 'excerpt', 'seo_title', 'seo_description', 'video_url']));
            if (array_key_exists('body_html', $input)) {
                if ($page->section === 'pages' && $page->editor_mode === 'layout') {
                    $this->fail('body_html', 'This is a composed page. Read it and update existing blocks instead.');
                }
                $this->prepare($page);
                $page->body = $input['body_html'];
            }
            if (isset($input['blocks'])) {
                if ($page->editor_mode !== 'layout' || $page->section !== 'pages') {
                    $this->fail('blocks', 'This material uses body_html, not blocks.');
                }
                $fields = collect(PageBlocks::fields($page->body ?? ''))->keyBy('key');
                $replacements = [];
                foreach ($input['blocks'] as $block) {
                    $field = $fields->get($block['key']);
                    if (! $field) {
                        $this->fail('blocks', 'Unknown block key. Read the current page again.');
                    }
                    if (in_array($field['kind'], ['image', 'link']) && ! preg_match('~^(https?://|/(?!/)|#|mailto:|tel:)~i', $block['value'])) {
                        $this->fail('blocks', 'Image and link values must be valid URLs.');
                    }
                    $replacements[] = [...$field, 'value' => $block['value']];
                }
                $page->body = PageBlocks::apply($page->body ?? '', $replacements);
            }
        });
    }

    public function publish(array $input): array
    {
        return $this->mutate($input, 'publication', function (ContentPage $page) use ($input) {
            $page->is_published = $input['is_published'];
            $page->published_at = $input['published_at'] ?? null;
        });
    }

    public function image(array $input): array
    {
        $path = null;
        if (! ($input['clear'] ?? false)) {
            $bytes = base64_decode($input['image_base64'], true);
            $info = $bytes !== false && strlen($bytes) <= 4 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
            $extension = match ($info['mime'] ?? '') {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null
            };
            if (! $extension) {
                $this->fail('image_base64', 'Provide a valid JPEG, PNG or WebP image of at most 4 MiB, encoded as raw base64.');
            }
            $path = 'mcp/images/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $bytes);
        }
        try {
            return $this->mutate($input, 'image', function (ContentPage $page) use ($path) {
                $this->prepare($page);
                $page->image_origin ??= ['slot' => $page->section === 'gods' ? 'god' : 'content'];
                $page->image = $path;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('public')->delete([$path, ...array_map(fn ($size) => ProductImages::variant($path, $size), [400, 800, 1200])]);
            }
            throw $error;
        }
    }

    public function create(array $input): array
    {
        $prefixes = ['communities' => '/hromady/', 'news' => '/novyny/', 'articles' => '/statti/', 'creativity' => '/tvorchist/', 'gods' => '/ridna-vira/bohy/', 'holidays' => '/ridna-vira/kalendar/', 'shrines' => '/ridna-vira/sviatyni/', 'rituals' => '/ridna-vira/obriady/', 'prayers' => '/ridna-vira/molytvy/', 'documents' => '/dokumenti/', 'books' => '/ridna-vira/knyhy/'];
        $prefix = $prefixes[$input['section']] ?? null;
        if (! $prefix || ! preg_match('~^'.preg_quote($prefix, '~').'[a-z0-9-]+$~D', $input['path'])) {
            $this->fail('path', 'Use an address inside the chosen section, with a Latin slug.');
        }
        if (ContentPage::withTrashed()->where('path', $input['path'])->exists()) {
            $this->fail('path', 'This address already exists, including deleted records.');
        }

        return DB::transaction(function () use ($input) {
            $page = ContentPage::create([...Arr::only($input, ['path', 'section', 'title', 'excerpt', 'seo_title', 'seo_description']), 'body' => $input['body_html'] ?? '', 'editor_mode' => 'reading', 'is_published' => false]);
            $this->audit($page->fresh(), 'create', null);

            return $this->get($page->id);
        });
    }

    public function revisions(int $id): array
    {
        $page = ContentPage::findOrFail($id);

        return ['id' => $id, 'version' => self::version($page), 'revisions' => $page->revisions()->latest('id')->limit(20)->get()->map(function ($revision) {
            $snapshot = json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR);

            return ['revision_id' => $revision->id, 'created_at' => $revision->created_at, ...Arr::only($snapshot, ['title', 'body', 'excerpt', 'image', 'seo_title', 'seo_description', 'editor_mode', 'is_published', 'published_at'])];
        })->all()];
    }

    private function prepare(ContentPage $page): void
    {
        $prepared = ContentEditor::prepare($page->attributesToArray());
        $page->fill(Arr::only($prepared, ['body', 'editor_mode', 'image', 'image_origin']));
    }

    private function mutate(array $input, string $action, \Closure $callback): array
    {
        return DB::transaction(function () use ($input, $action, $callback) {
            $page = ContentPage::lockForUpdate()->findOrFail($input['id']);
            $before = self::version($page);
            if (! hash_equals($before, $input['expected_version'])) {
                $this->fail('expected_version', 'CONFLICT: the page changed. Read it again before applying changes.');
            }
            $callback($page);
            if ($page->isDirty()) {
                $page->save();
                $this->audit($page->fresh(), $action, $before);
            }

            return $this->get($page->id);
        });
    }

    private function audit(ContentPage $page, string $action, ?string $before): void
    {
        DB::table('site_mcp_audits')->insert(['site_mcp_token_id' => request()->attributes->get('site.mcp.token')?->id, 'user_id' => auth()->id(), 'content_page_id' => $page->id, 'action' => $action, 'before_version' => $before, 'after_version' => self::version($page), 'created_at' => now()]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
