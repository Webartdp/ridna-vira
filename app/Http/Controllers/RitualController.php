<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RitualController extends Controller
{
    private ?array $catalogue = null;

    public function index(): View
    {
        return view('pages.rituals', $this->catalogue());
    }

    public function show(string $ritual): View
    {
        $entry = $this->entry($ritual);
        $content = isset($entry['page'])
            ? file_get_contents(resource_path('content/rituals/'.$entry['page'])) : null;

        return view('pages.ritual', [
            'entry' => $entry,
            'slug' => $ritual,
            'content' => $content,
            'group' => collect($this->catalogue()['groups'])->firstWhere('id', $entry['category'])['title'],
        ]);
    }

    public function download(string $ritual, string $format): BinaryFileResponse
    {
        $file = collect($this->entry($ritual)['files'] ?? [])->firstWhere('format', $format);
        abort_unless($file !== null && preg_match('~^documents/[a-zA-Z0-9._-]+$~', $file['path']), 404);
        $path = public_path('assets/rituals/'.$file['path']);
        abort_unless(is_file($path), 404);

        return response()->download($path, $ritual.'.'.$format, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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
