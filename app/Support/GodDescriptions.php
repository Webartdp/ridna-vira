<?php

declare(strict_types=1);

namespace App\Support;

final class GodDescriptions
{
    private ?array $entries = null;

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->entries ??= json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/resources/content/gods.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }
}
