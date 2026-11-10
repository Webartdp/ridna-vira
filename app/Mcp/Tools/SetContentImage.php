<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class SetContentImage extends ContentTool
{
    protected string $operation = 'image';

    protected string $description = 'Replace a material primary image using base64 JPEG, PNG or WebP (4 MiB maximum), or clear it. Updates its card, detail page and social preview, preserving gallery photos. Generates responsive WebP sizes. Requires the version from get-content. Does not fetch URLs or read server files.';
}
