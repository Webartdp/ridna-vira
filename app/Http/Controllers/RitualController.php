<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Cms;
use App\Support\DownloadCanonical;
use App\Support\RitualReading;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RitualController extends Controller
{
    private ?array $catalogue = null;

    public function index(): View
    {
        $catalogue = $this->catalogue();
        $catalogue['entries'] = collect(Cms::merge('rituals', $catalogue['entries'], ''))->keyBy('slug')->all();

        return view('pages.rituals', $catalogue);
    }

    public function show(string $ritual, RitualReading $reading): View
    {
        $entry = $this->entry($ritual);
        $content = isset($entry['page'])
            ? file_get_contents(resource_path('content/rituals/'.$entry['page'])) : null;
        if ($content !== null) {
            $content = $reading->format($content, $ritual);
        }

        return view('pages.ritual', [
            'entry' => $entry,
            'slug' => $ritual,
            'content' => $content,
            'group' => collect($this->catalogue()['groups'])->firstWhere('id', $entry['category'])['title'],
        ]);
    }

    public function download(string $ritual, string $format): BinaryFileResponse
    {
        $entry = $this->entry($ritual);
        $file = collect($entry['files'] ?? [])->firstWhere('format', $format);
        abort_unless($file !== null && preg_match('~^documents/[a-zA-Z0-9._-]+$~', $file['path']), 404);
        $path = public_path('assets/rituals/'.$file['path']);
        abort_unless(is_file($path), 404);

        $canonicalRitual = $entry['download_canonical_ritual'] ?? $ritual;
        $canonicalEntry = $this->entry($canonicalRitual);
        $canonicalFormat = $canonicalEntry['download_canonical_format'] ?? $format;
        $canonicalFile = collect($canonicalEntry['files'] ?? [])->firstWhere('format', $canonicalFormat);
        $canonicalDownload = $canonicalFile !== null && is_file(public_path('assets/rituals/'.$canonicalFile['path']))
            ? [$canonicalRitual, $canonicalFormat] : [$ritual, $format];

        return response()->download($path, $ritual.'.'.$format, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Link' => DownloadCanonical::header(
                route('faith.rituals.download', $canonicalDownload, absolute: false),
                ! empty($canonicalEntry['download_canonical_page']) ? route('faith.rituals.show', $canonicalRitual, absolute: false) : null,
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function catalogue(): array
    {
        return $this->catalogue ??= json_decode(
            (string) file_get_contents(resource_path('content/rituals/catalogue.json')),
            true, 512, JSON_THROW_ON_ERROR,
        );
    }

    private function entry(string $slug): array
    {
        $entries = $this->catalogue()['entries'];
        abort_unless(isset($entries[$slug]), 404);

        return $entries[$slug];
    }
}
