<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DocumentContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentDownloadController extends Controller
{
    public function show(string $document): View
    {
        $entry = $this->resolveEntry($document);
        $relativePath = $this->normalizeRelativePath((string) ($entry['path'] ?? ''));
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($relativePath, PATHINFO_EXTENSION)));
        $absolutePath = $this->resolveAbsolutePath($relativePath);
        $title = trim((string) ($entry['title'] ?? $document));

        $editablePath = resource_path('content/documents/'.$document.'.html');
        $content = null;
        $contentSource = null;

        if (is_file($editablePath)) {
            $content = DocumentContent::clean((string) file_get_contents($editablePath));
            $contentSource = 'editable';
        } elseif ($extension === 'html' && is_file($absolutePath)) {
            $content = DocumentContent::clean((string) file_get_contents($absolutePath));
            $contentSource = 'imported';
        }

        return view('pages.document', [
            'documentKey' => $document,
            'documentTitle' => $title,
            'extension' => strtoupper($extension ?: 'FILE'),
            'size' => (int) ($entry['size'] ?? (is_file($absolutePath) ? filesize($absolutePath) : 0)),
            'content' => $content,
            'contentSource' => $contentSource,
            'hasFile' => is_file($absolutePath),
        ]);
    }

    public function download(string $document): BinaryFileResponse|Response
    {
        $entry = $this->resolveEntry($document);
        $relativePath = $this->normalizeRelativePath((string) ($entry['path'] ?? ''));
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($relativePath, PATHINFO_EXTENSION)));
        $title = trim((string) ($entry['title'] ?? $document));
        $absolutePath = $this->resolveAbsolutePath($relativePath);

        abort_unless(is_file($absolutePath), 404, 'Файл документа відсутній.');

        $safeTitle = preg_replace('/[^\pL\pN._ -]+/u', '-', $title) ?: $document;
        $downloadName = trim($safeTitle).($extension !== '' ? '.'.$extension : '');

        return response()->download($absolutePath, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function resolveEntry(string $document): array
    {
        $manifestPath = public_path('assets/documents/manifest.json');

        abort_unless(is_file($manifestPath), 404, 'Сховище документів ще не сформоване.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        abort_unless(is_array($manifest) && isset($manifest[$document]) && is_array($manifest[$document]), 404, 'Документ не знайдено.');

        return $manifest[$document];
    }

    private function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $relativePath = preg_replace('#^public/#', '', $relativePath) ?: $relativePath;

        abort_unless(
            $relativePath !== ''
            && ! str_contains($relativePath, '..')
            && str_starts_with($relativePath, 'assets/documents/'),
            404,
            'Некоректний шлях документа.'
        );

        return $relativePath;
    }

    private function resolveAbsolutePath(string $relativePath): string
    {
        return public_path($relativePath);
    }
}
