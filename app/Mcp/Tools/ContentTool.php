<?php

namespace App\Mcp\Tools;

use App\Models\ContentPage;
use App\Services\McpContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class ContentTool extends Tool
{
    protected string $operation;

    public function schema(JsonSchema $schema): array
    {
        $id = ['id' => $schema->integer()->min(1)->required()];
        $version = ['expected_version' => $schema->string()->min(64)->max(64)->description('Version returned by get-content. Stale versions are rejected.')->required()];
        $fields = [
            'title' => $schema->string()->max(255), 'excerpt' => $schema->string()->max(1000),
            'seo_title' => $schema->string()->max(255), 'seo_description' => $schema->string()->max(500),
            'body_html' => $schema->string()->max(500000)->description('Reading text only. Preserve prayer and praise semantic classes. Composed pages use blocks instead.'),
        ];

        return match ($this->operation) {
            'list' => ['section' => $schema->string()->enum(array_keys(ContentPage::sections())), 'search' => $schema->string()->max(190), 'page' => $schema->integer()->min(1), 'limit' => $schema->integer()->min(1)->max(50)],
            'get', 'revisions' => $id,
            'update' => [...$id, ...$version, ...$fields, 'video_url' => $schema->string()->description('YouTube HTTPS URL; empty string removes it.'), 'blocks' => $schema->array()->max(100)->items($schema->object(['key' => $schema->string()->required(), 'value' => $schema->string()->required()]))],
            'publish' => [...$id, ...$version, 'is_published' => $schema->boolean()->required(), 'published_at' => $schema->string()->description('ISO 8601 datetime with timezone, or omit for immediate publication.')],
            'image' => [...$id, ...$version, 'image_base64' => $schema->string()->description('Raw base64 of a JPEG, PNG or WebP file, maximum decoded size 4 MiB. No URLs or local paths.'), 'clear' => $schema->boolean()->description('True removes the primary cover. Otherwise image_base64 is required.')],
            'create' => [...$fields, 'title' => $schema->string()->max(255)->required(), 'path' => $schema->string()->max(255)->required(), 'section' => $schema->string()->enum(array_values(array_diff(array_keys(ContentPage::sections()), ['pages'])))->required()],
        };
    }

    public function handle(Request $request, McpContent $content): Response|ResponseFactory
    {
        $scope = match ($this->operation) {
            'update', 'create' => 'content:write', 'publish' => 'content:publish', 'image' => 'media:write', default => 'content:read'
        };
        if (! request()->attributes->get('site.mcp.token')?->allows($scope)) {
            return Response::error('Permission denied: token does not allow '.$scope);
        }
        try {
            $input = $request->validate($this->rules());
            $result = match ($this->operation) {
                'list' => $content->listing($input), 'get' => $content->get($input['id']),
                'update' => $content->update($input), 'create' => $content->create($input),
                'publish' => $content->publish($input), 'image' => $content->image($input),
                'revisions' => $content->revisions($input['id']),
            };

            return Response::structured($result);
        } catch (ValidationException $error) {
            return Response::error(json_encode(['validation' => $error->errors()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (ModelNotFoundException) {
            return Response::error('Content not found. Use list-content to find an existing record.');
        } catch (\Throwable $error) {
            report($error);

            return Response::error('The operation failed. No partial content update was committed. Check the server log.');
        }
    }

    private function rules(): array
    {
        $id = ['id' => 'required|integer|min:1'];
        $version = ['expected_version' => 'required|string|regex:/^[a-f0-9]{64}$/D'];
        $fields = ['title' => 'sometimes|required|string|max:255', 'excerpt' => 'sometimes|nullable|string|max:1000', 'seo_title' => 'sometimes|nullable|string|max:255', 'seo_description' => 'sometimes|nullable|string|max:500', 'body_html' => 'sometimes|string|max:500000'];

        return match ($this->operation) {
            'list' => ['section' => ['sometimes', Rule::in(array_keys(ContentPage::sections()))], 'search' => 'sometimes|string|max:190', 'page' => 'sometimes|integer|min:1|max:100000', 'limit' => 'sometimes|integer|min:1|max:50'],
            'get', 'revisions' => $id,
            'update' => [...$id, ...$version, ...$fields, 'video_url' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:~^https://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)[A-Za-z0-9_-]{11}(?:[?&#/].*)?$~D'], 'blocks' => 'sometimes|array|max:100', 'blocks.*' => 'array:key,value', 'blocks.*.key' => 'required|string|max:1000|distinct', 'blocks.*.value' => 'present|string|max:500000'],
            'publish' => [...$id, ...$version, 'is_published' => 'required|boolean', 'published_at' => 'sometimes|nullable|date'],
            'image' => [...$id, ...$version, 'clear' => 'sometimes|boolean', 'image_base64' => 'required_unless:clear,true|string|max:5592408'],
            'create' => [...$fields, 'title' => 'required|string|max:255', 'path' => 'required|string|max:255', 'section' => ['required', Rule::in(array_values(array_diff(array_keys(ContentPage::sections()), ['pages'])))]],
        };
    }
}
