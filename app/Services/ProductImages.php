<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImages
{
    public static function variant(string $path, int $width): string
    {
        return pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME).'-'.$width.'.webp';
    }

    public function generate(string $path): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return;
        }
        $dimensions = @getimagesize($disk->path($path));
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 24000000) {
            throw ValidationException::withMessages(['images' => 'Зображення має бути не більше 24 мегапікселів.']);
        }
        $limit = ini_get('memory_limit');
        if ($limit !== '-1') {
            $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
                'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1
            };
            if ($bytes - memory_get_usage(true) < $dimensions[0] * $dimensions[1] * 7 + 16000000) {
                throw ValidationException::withMessages(['images' => 'Зображення надто велике для обробки. Зменште його до 1600 пікселів.']);
            }
        }
        $source = @imagecreatefromstring($disk->get($path));
        if (! $source) {
            throw ValidationException::withMessages(['images' => 'Не вдалося прочитати зображення.']);
        }
        foreach ([400, 800, 1200] as $width) {
            $ratio = min(1, $width / $dimensions[0], $width / $dimensions[1]);
            $target = imagecreatetruecolor(max(1, (int) round($dimensions[0] * $ratio)), max(1, (int) round($dimensions[1] * $ratio)));
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $dimensions[0], $dimensions[1]);
            ob_start();
            imagewebp($target, null, 90);
            $disk->put(self::variant($path, $width), ob_get_clean());
            unset($target);
        }
        unset($source);
    }
}
