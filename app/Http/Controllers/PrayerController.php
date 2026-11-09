<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Cms;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PrayerController extends Controller
{
    private ?array $catalogue = null;

    public function index(): View
    {
        $catalogue = $this->catalogue();
        $catalogue['entries'] = collect(Cms::merge('prayers', $catalogue['entries'], ''))->keyBy('slug')->all();

        return view('pages.prayers', $catalogue);
    }

    public function show(string $prayer): View
    {
        $entry = $this->entry($prayer);
        $blocks = isset($entry['page']) ? json_decode(file_get_contents(resource_path('content/prayers/'.$entry['page'])), true, 512, JSON_THROW_ON_ERROR) : [];
        $reading = [];
        foreach ($blocks as $block) {
            $last = count($reading) - 1;
            if (in_array($block['type'], ['prayer', 'praise', 'spell', 'direction', 'note'], true)) {
                if ($last >= 0 && $reading[$last]['type'] === $block['type']) {
                    $reading[$last]['paragraphs'][] = $block['html'];
                } else {
                    $reading[] = ['type' => $block['type'], 'paragraphs' => [$block['html']]];
                }
            } else {
                $reading[] = $block;
            }
        }

        return view('pages.prayer', [
            'entry' => $entry, 'slug' => $prayer, 'reading' => $reading,
            'group' => collect($this->catalogue()['groups'])->firstWhere('id', $entry['category'])['title'],
        ]);
    }

    public function download(string $prayer, string $format): BinaryFileResponse
    {
        $file = collect($this->entry($prayer)['files'] ?? [])->firstWhere('format', $format);
        abort_unless($file !== null && preg_match('~^files/[a-zA-Z0-9._-]+$~', $file['path']), 404);
        $path = public_path('assets/prayers/'.$file['path']);
        abort_unless(is_file($path), 404);

        return response()->download($path, $prayer.'.'.$format, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'image/vnd.djvu',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function catalogue(): array
    {
        return $this->catalogue ??= json_decode(file_get_contents(resource_path('content/prayers/catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    private function entry(string $slug): array
    {
        $entries = $this->catalogue()['entries'];
        abort_unless(isset($entries[$slug]), 404);

        return $entries[$slug];
    }
}
