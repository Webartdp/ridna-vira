<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class UpdateContent extends ContentTool
{
    protected string $operation = 'update';

    protected string $description = 'Update selected text or SEO fields on an existing material. Changes to published materials become public immediately. Pass only intended changes and the version from get-content. Use blocks for composed pages. Publication state is preserved; changes create revisions and audit entries.';
}
