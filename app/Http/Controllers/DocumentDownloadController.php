<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentDownloadController extends Controller
{
    /**
     * @return BinaryFileResponse|Response
     */
    public function __invoke(string $document): BinaryFileResponse|Response
    {
        $manifestPath = public_path('assets/documents/manifest.json');

        abort_unless(is_file($manifestPath), 404, 'Сховище документів ще не сформоване.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        abort_unless(is_array($manifest) && isset($manifest[$document]), 404, 'Документ не знайдено.');

        $entry = $manifest[$document];
        $relativePath = ltrim((string) ($entry['path'] ?? ''), '/');
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($relativePath, PATHINFO_EXTENSION)));
        $title = trim((string) ($entry['title'] ?? $document));
        $absolutePath = public_path($relativePath);

        abort_unless(
            $relativePath !== ''
            && !str_contains($relativePath, '..')
            && str_starts_with($relativePath, 'assets/documents/')
            && is_file($absolutePath),
            404,
            'Файл документа відсутній.'
        );

        if ($extension === 'html') {
            return response()->file($absolutePath, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $safeTitle = preg_replace('/[\\\/:*?"<>|]+/u', '-', $title) ?: $document;
        $downloadName = $safeTitle . ($extension !== '' ? '.' . $extension : '');

        return response()->download($absolutePath, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
