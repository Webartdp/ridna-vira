<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class PublishContent extends ContentTool
{
    protected string $operation = 'publish';

    protected string $description = 'Explicitly publish, schedule or unpublish a material. Changes public visibility. Requires its current expected_version and is_published. Omit published_at for immediate publication; future ISO datetime schedules publication.';
}
