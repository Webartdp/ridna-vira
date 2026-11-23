<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramProductModerationTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.telegram.org/bot123456:abc_DE-f/';

    private const ADMIN_CHAT = 424242;

    private array $updates = [];

    private int $nextUpdate = 100;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.bot_token' => '123456:abc_DE-f',
            'services.telegram.chat_id' => (string) self::ADMIN_CHAT,
            'app.url' => 'https://ridna.example.test',
        ]);
        $this->withoutVite();
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            self::API.'sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 17]]),
            self::API.'getUpdates' => function () {
                $updates = $this->updates;
                $this->updates = [];

                return Http::response(['ok' => true, 'result' => $updates]);
            },
            self::API.'answerCallbackQuery' => Http::response(['ok' => true]),
            self::API.'editMessageReplyMarkup' => Http::response(['ok' => true]),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'maker']));
    }

    public function test_admin_can_publish_from_the_first_part_of_the_alert_and_open_the_product(): void
    {
        $product = $this->submitDraft(['description' => str_repeat('Текст опису виробу. ', 150)]);
        $this->get(route('shop.product', $product))->assertNotFound();

        $messages = $this->requests('sendMessage');
        $this->assertGreaterThan(1, $messages->count());
        $data = 'product:'.$product->id.':mod:'.$product->moderation_token;
        $this->assertMatchesRegularExpression('/^product:[0-9]+:mod:[a-zA-Z0-9]{24}$/D', $data);
        $this->assertLessThanOrEqual(64, strlen($data));
        $this->assertSame([
            [['text' => 'Опублікувати', 'callback_data' => $data]],
        ], $messages->first()['reply_markup']['inline_keyboard']);
        foreach ($messages->skip(1) as $message) {
            $this->assertArrayNotHasKey('reply_markup', $message->data());
        }

        $update = $this->callbackUpdate($data);
        $this->poll($update);
        $this->assertTrue($product->refresh()->is_active);
        $this->assertNull($product->moderation_token);
        $this->get(route('shop.product', $product))->assertOk();
        $answer = $this->requests('answerCallbackQuery')->sole();
        $this->assertSame($update['callback_query']['id'], $answer['callback_query_id']);
        $this->assertSame('Виріб опубліковано в крамниці.', $answer['text']);
        $editedMessage = $this->requests('editMessageReplyMarkup')->sole();
        $this->assertEquals(self::ADMIN_CHAT, $editedMessage['chat_id']);
        $this->assertEquals(17, $editedMessage['message_id']);
        $storefrontButton = $editedMessage['reply_markup']['inline_keyboard'][0][0];
        $this->assertStringContainsString('Відкрити в крамниці', $storefrontButton['text']);
        $this->assertSame('https://ridna.example.test'.route('shop.product', $product, false), $storefrontButton['url']);
        $this->assertArrayNotHasKey('callback_data', $storefrontButton);

        $publishedAt = $product->updated_at->toDateTimeString();
        $this->travel(2)->seconds();
        $this->poll($this->callbackUpdate($data));
        $this->assertTrue($product->refresh()->is_active);
        $this->assertSame($publishedAt, $product->updated_at->toDateTimeString());
        $this->assertSame('Виріб уже опубліковано.', $this->requests('answerCallbackQuery')->last()['text']);
    }

    public function test_callback_requires_the_bot_administrator_as_both_sender_and_private_chat(): void
    {
        $product = $this->submitDraft();
        $data = $this->latestPublishData();
        $inline = $this->callbackUpdate($data);
        unset($inline['callback_query']['message']);
        $inline['callback_query']['inline_message_id'] = 'other-message';

        $this->poll(
            $inline,
            $this->callbackUpdate($data, sender: 987654),
            $this->callbackUpdate($data, chat: 987654),
            $this->callbackUpdate($data, type: 'group'),
        );

        $this->assertFalse($product->refresh()->is_active);
        $this->assertNotNull($product->moderation_token);
        $this->assertCount(0, $this->requests('editMessageReplyMarkup'));
        $this->assertSame(array_fill(0, 4, 'Ця дія недоступна.'), $this->requests('answerCallbackQuery')->map(fn (Request $request) => $request['text'])->all());
        $this->assertContains('callback_query', $this->requests('getUpdates')->sole()['allowed_updates']);
    }

    public function test_a_same_second_edit_invalidates_the_old_button_and_sends_a_new_one(): void
    {
        $this->freezeTime();
        $product = $this->submitDraft();
        $oldData = $this->latestPublishData();
        $this->put(route('maker.products.update', $product), [
            ...$this->draftFields(),
            'name' => 'Миска з новим розписом',
            'stock_before' => $product->stock,
        ])->assertRedirect(route('maker.index'));

        $newData = $this->latestPublishData();
        $this->assertNotSame($oldData, $newData);
        $this->poll($this->callbackUpdate($oldData));
        $this->assertFalse($product->refresh()->is_active);
        $this->assertSame('Миска з новим розписом', $product->name);
        $this->assertSame('Ця версія виробу більше не на модерації.', $this->requests('answerCallbackQuery')->sole()['text']);
        $this->poll($this->callbackUpdate($newData));
        $this->assertTrue($product->refresh()->is_active);
    }

    public function test_hiding_or_deleting_a_draft_invalidates_its_publish_button(): void
    {
        $hidden = $this->submitDraft();
        $hiddenData = $this->latestPublishData();
        $this->patch(route('maker.products.hide', $hidden))->assertRedirect();
        $this->poll($this->callbackUpdate($hiddenData));
        $this->assertFalse($hidden->refresh()->is_active);
        $this->assertNull($hidden->moderation_token);

        $deleted = $this->submitDraft(['name' => 'Миска з іншої добірки']);
        $deletedData = $this->latestPublishData();
        $this->delete(route('maker.products.destroy', $deleted))->assertRedirect(route('maker.index'));
        $this->poll($this->callbackUpdate($deletedData));
        $this->assertSoftDeleted($deleted);

        $deleted->refresh()->restore();
        $this->poll($this->callbackUpdate($deletedData));
        $this->assertFalse($deleted->refresh()->is_active);
        $this->assertNull($deleted->moderation_token);
    }

    public function test_failed_status_response_does_not_block_the_following_publish_update(): void
    {
        $product = $this->submitDraft();
        $update = $this->callbackUpdate($this->latestPublishData());
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            self::API.'getUpdates' => Http::sequence()
                ->push(['ok' => true, 'result' => [
                    ['update_id' => $update['update_id'] - 1, 'message' => [
                        'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'private'],
                        'text' => '/status',
                    ]],
                    $update,
                ]])
                ->push(['ok' => true, 'result' => []]),
            self::API.'sendMessage' => Http::response(['ok' => false], 503),
            self::API.'answerCallbackQuery' => Http::response(['ok' => true]),
            self::API.'editMessageReplyMarkup' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->artisan('telegram:alerts')->assertSuccessful();

        $this->assertTrue($product->refresh()->is_active);
        $this->assertSame($update['update_id'] + 1, $this->requests('getUpdates')->last()['offset']);
        $this->assertSame('Виріб опубліковано в крамниці.', $this->requests('answerCallbackQuery')->sole()['text']);
    }

    public function test_publication_confirmation_is_sent_if_telegram_cannot_update_the_button(): void
    {
        $product = $this->submitDraft();
        $data = $this->latestPublishData();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            self::API.'getUpdates' => Http::sequence()
                ->push(['ok' => true, 'result' => [$this->callbackUpdate($data)]])
                ->push(['ok' => true, 'result' => [$this->callbackUpdate($data)]]),
            self::API.'answerCallbackQuery' => Http::response(['ok' => false], 400),
            self::API.'editMessageReplyMarkup' => Http::response(['ok' => false], 400),
            self::API.'sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->artisan('telegram:alerts')->assertSuccessful();

        $this->assertTrue($product->refresh()->is_active);
        $confirmation = $this->requests('sendMessage')->sole();
        $this->assertStringContainsString('Виріб опубліковано', $confirmation['text']);
        $this->assertSame('https://ridna.example.test'.route('shop.product', $product, false),
            $confirmation['reply_markup']['inline_keyboard'][0][0]['url']);
    }

    private function submitDraft(array $fields = []): Product
    {
        $fields = [...$this->draftFields(), ...$fields];
        $this->post(route('maker.products.store'), [
            ...$fields,
            'images' => [UploadedFile::fake()->image('bowl.jpg')],
        ])->assertRedirect(route('maker.index'));

        return Product::where('name', $fields['name'])->sole();
    }

    private function draftFields(): array
    {
        return ['name' => 'Глиняна миска', 'description' => 'Миска ручної роботи.', 'price' => '100', 'stock' => 2];
    }

    private function latestPublishData(): string
    {
        return $this->requests('sendMessage')->last()['reply_markup']['inline_keyboard'][0][0]['callback_data'];
    }

    private function callbackUpdate(string $data, int $sender = self::ADMIN_CHAT, int $chat = self::ADMIN_CHAT, string $type = 'private'): array
    {
        $updateId = $this->nextUpdate++;

        return [
            'update_id' => $updateId,
            'callback_query' => [
                'id' => '212383833663'.$updateId,
                'from' => ['id' => $sender, 'is_bot' => false],
                'message' => ['message_id' => 17, 'chat' => ['id' => $chat, 'type' => $type]],
                'data' => $data,
            ],
        ];
    }

    private function poll(array ...$updates): void
    {
        $this->updates = $updates;
        $this->artisan('telegram:alerts')->assertSuccessful();
    }

    private function requests(string $method): Collection
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::API.$method)
            ->map(fn (array $exchange) => $exchange[0])->values();
    }
}
