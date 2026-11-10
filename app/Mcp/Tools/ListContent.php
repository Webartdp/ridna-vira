<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListContent extends ContentTool
{
    protected string $operation = 'list';

    protected string $description = 'Search CMS materials by title or path, optionally filter by section. Includes drafts; excludes deleted records. Paginated. Read a record before changing it.';
}
