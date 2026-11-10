<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
class CreateContent extends ContentTool
{
    protected string $operation = 'create';

    protected string $description = 'Create a new unpublished material in an existing content section using a unique section path. Never publishes automatically. Use publish-content separately after review.';
}
