<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ArticleResource\Pages\EditArticleEntry;
use App\Filament\Resources\ArticleResource\Pages\ManageArticleEntries;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\ContentPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use App\Services\ManageSiteUsers;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountsAndRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function user(string $role = 'user', array $extra = []): User
    {
        $user = User::factory()->create(['password' => 'CurrentPassword123']);
        $user->forceFill(['role' => $role, ...$extra])->save();

        return $user->fresh();
    }

    private function order(?User $owner, array $extra = []): Order
    {
        $product = Product::create(['name' => 'Книга', 'slug' => 'book-'.Str::random(8), 'price_minor' => 15000, 'stock' => 5, 'is_active' => true]);

        return app(Checkout::class)->create(['customer_name' => 'Покупець', 'email' => $owner?->email ?? 'guest@example.com', 'phone' => '+380991234567', 'delivery_method' => 'pickup', 'delivery_address' => 'Самовивіз', ...$extra], [$product->id => 1], (string) Str::uuid(), $owner?->id);
    }

    public function test_public_auth_pages_and_account_are_private_and_not_in_sitemap(): void
    {
        foreach (['/uviyty', '/reiestratsiia', '/parol/vidnovyty'] as $path) {
            $response = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->get('/kabinet')->assertRedirect(route('login'));
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/kabinet', false)->assertDontSee('/uviyty', false);
    }

    public function test_registration_cannot_choose_privileged_role_and_sends_verification(): void
    {
        $this->post('/reiestratsiia', ['name' => 'Нова людина', 'email' => ' New@Example.COM ', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'role' => 'admin', 'is_admin' => true, 'email_verified_at' => now()])->assertRedirect(route('account.index'));
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('StrongPassword123', $user->password));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/kabinet')->assertOk()->assertSee('Мої замовлення');
        $this->get('/admin')->assertForbidden();
    }

    public function test_login_logout_and_blocked_accounts_are_enforced(): void
    {
        $user = $this->user('user', ['email' => 'UPPER@example.com']);
        $this->post('/uviyty', ['email' => 'upper@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/uviyty', ['email' => 'upper@example.com', 'password' => 'CurrentPassword123'])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);
        $user->forceFill(['is_active' => false])->save();
        Auth::forgetGuards();
        $this->get('/kabinet')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/uviyty', ['email' => $user->email, 'password' => 'CurrentPassword123'])->assertSessionHasErrors('email');
        $user->forceFill(['is_active' => true])->save();
        $this->actingAs($user)->post('/vyity')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_roles_restrict_admin_routes_and_model_policies(): void
    {
        $routes = ['/admin/articles' => 'content', '/admin/news' => 'content', '/admin/gods' => 'content', '/admin/communities' => 'content', '/admin/calendar-settings' => 'content', '/admin/products' => 'shop', '/admin/orders' => 'shop', '/admin/site-settings' => 'admin', '/admin/users' => 'admin'];
        foreach (['admin', 'editor', 'shop_manager', 'user'] as $role) {
            $this->flushSession();
            Auth::forgetGuards();
            $user = $this->user($role);
            $this->actingAs($user);
            foreach ($routes as $path => $area) {
                $allowed = $role === 'admin' || ($role === 'editor' && $area === 'content') || ($role === 'shop_manager' && $area === 'shop');
                $response = $this->get($path);
                if ($allowed) {
                    $response->assertOk();
                } else {
                    $response->assertForbidden();
                }
            }
            $this->assertSame($user->canManage('content'), Gate::forUser($user)->allows('create', ContentPage::class));
            $this->assertSame($user->canManage('shop'), Gate::forUser($user)->allows('create', Product::class));
            $this->assertSame($role === 'admin', Gate::forUser($user)->allows('create', User::class));
        }
    }

    public function test_admin_can_manage_users_without_exposing_password_and_cannot_lock_himself_out(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        Livewire::test(ManageUsers::class)->mountAction('create')->set('mountedActions.0.data.name', 'Редактор')->set('mountedActions.0.data.email', 'editor@example.com')->set('mountedActions.0.data.role', 'editor')->set('mountedActions.0.data.is_active', true)->set('mountedActions.0.data.password', 'EditorPassword123')->callMountedAction()->assertHasNoActionErrors();
        $editor = User::where('email', 'editor@example.com')->firstOrFail();
        $this->assertSame('editor', $editor->role);
        $this->assertFalse($editor->is_admin);
        $this->assertTrue(Hash::check('EditorPassword123', $editor->password));
        $component = Livewire::test(ManageUsers::class)->mountTableAction('edit', $editor);
        $this->assertEmpty($component->get('mountedActions.0.data.password'));
        $old = $editor->password;
        $component->set('mountedActions.0.data.name', 'Оновлений редактор')->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertSame($old, $editor->fresh()->password);
        Livewire::test(ManageUsers::class)->mountTableAction('edit', $admin)->set('mountedActions.0.data.role', 'user')->callMountedTableAction()->assertHasTableActionErrors();
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertDatabaseCount('user_access_audits', 2);
    }

    public function test_non_admin_cannot_call_user_service_or_settings_actions(): void
    {
        $editor = $this->user('editor');
        $this->actingAs($editor);
        Livewire::test(SiteSettings::class)->assertForbidden();
        $this->expectException(HttpException::class);
        app(ManageSiteUsers::class)->save(null, ['name' => 'Зловмисник', 'email' => 'x@example.com', 'role' => 'admin', 'is_active' => true, 'password' => 'AnyPassword123']);
    }

    public function test_orders_are_only_visible_to_their_owner_and_guest_email_does_not_auto_claim(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $own = $this->order($alice);
        $other = $this->order($bob);
        $guest = $this->order(null, ['email' => $alice->email]);
        $this->actingAs($alice)->get('/kabinet')->assertOk()->assertSee(route('account.order', $own->reference), false)->assertDontSee($other->reference)->assertDontSee($guest->reference);
        $this->get(route('account.order', $other->reference))->assertNotFound();
        $this->get(route('account.order', $own->reference))->assertOk()->assertSee('Ваше замовлення');
        $this->assertNull($guest->fresh()->user_id);
    }

    public function test_guest_order_claim_requires_verified_matching_email_and_unguessable_token(): void
    {
        $user = $this->user('user', ['email_verified_at' => null]);
        $order = $this->order(null, ['email' => $user->email]);
        $this->actingAs($user)->post('/kabinet/zamovlennia/dodaty', ['token' => $order->access_token])->assertRedirect(route('verification.notice'));
        $user->markEmailAsVerified();
        $this->post('/kabinet/zamovlennia/dodaty', ['token' => Str::random(64)])->assertNotFound();
        $other = $this->order(null, ['email' => 'other@example.com']);
        $this->post('/kabinet/zamovlennia/dodaty', ['token' => $other->access_token])->assertNotFound();
        $this->post('/kabinet/zamovlennia/dodaty', ['token' => $order->access_token])->assertRedirect(route('account.index'));
        $this->assertSame($user->id, $order->fresh()->user_id);
    }

    public function test_profile_edits_require_current_password_and_cannot_change_role(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $data = ['name' => 'Нове ім’я', 'email' => 'changed@example.com', 'phone' => '+380991234567', 'delivery_address' => 'Київ', 'role' => 'admin', 'is_admin' => true];
        $this->put('/kabinet/profil', [...$data, 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->put('/kabinet/profil', [...$data, 'current_password' => 'CurrentPassword123'])->assertRedirect();
        $user->refresh();
        $this->assertSame('changed@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->is_admin);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_verification_requires_signed_link_and_same_logged_in_user(): void
    {
        $user = $this->user('user', ['email_verified_at' => null]);
        $this->actingAs($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get($url)->assertRedirect(route('account.index'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_uses_single_use_token_and_keeps_role(): void
    {
        $user = $this->user('editor');
        $this->post('/parol/vidnovyty', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $input = ['email' => $user->email, 'token' => $token, 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123', 'role' => 'admin'];
        $this->post('/parol/skynuty', $input)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->assertSame('editor', $user->fresh()->role);
        $this->post('/parol/skynuty', $input)->assertSessionHasErrors('email');
    }

    public function test_http_checkout_uses_authenticated_owner_and_ignores_injected_user_id(): void
    {
        config(['shop.wayforpay_account' => 'test', 'shop.wayforpay_secret' => 'test-secret']);
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($user);
        $product = Product::create(['name' => 'Товар', 'slug' => 'owned-order', 'price_minor' => 10000, 'stock' => 3, 'is_active' => true]);
        $token = (string) Str::uuid();
        $this->withSession(['checkout_token' => $token, 'cart' => [$product->id => 1]])->post('/zamovlennia', ['checkout_token' => $token, 'customer_name' => 'Покупець', 'email' => $other->email, 'user_id' => $other->id, 'phone' => '+380991234567', 'delivery_method' => 'pickup', 'delivery_address' => 'Самовивіз', 'consent' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($user->email, $order->email);
        $this->assertSame(1, $user->orders()->count());
        $this->assertSame(0, $other->orders()->count());
    }

    public function test_admin_password_reset_invalidates_existing_account_session(): void
    {
        $user = $this->user();
        $this->post('/uviyty', ['email' => $user->email, 'password' => 'CurrentPassword123'])->assertRedirect();
        $this->get('/kabinet')->assertOk();
        $user->forceFill(['password' => 'DifferentPassword123'])->save();
        Auth::forgetGuards();
        $this->get('/kabinet')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_article_table_keeps_editing_available_with_compact_secondary_actions(): void
    {
        $this->actingAs($this->user('editor'));
        $article = ContentPage::create(['path' => '/statti/role-test', 'section' => 'articles', 'title' => 'Стаття для редактора', 'excerpt' => 'Короткий зміст матеріалу.', 'body' => '<p>Текст.</p>']);
        Livewire::test(ManageArticleEntries::class)->assertCanSeeTableRecords([$article]);
        $editPath = '/admin/articles/'.$article->getKey().'/edit';
        $this->get('/admin/articles')->assertOk()->assertSee($editPath, false);
        $this->get($editPath)->assertOk()->assertSee('wire:submit="save"', false);
        $this->get('/admin/news/'.$article->getKey().'/edit')->assertNotFound();
        Livewire::test(EditArticleEntry::class, ['record' => $article->getKey()])->set('data.title', 'Оновлена стаття')->call('save')->assertHasNoErrors();
        $this->assertSame('Оновлена стаття', $article->fresh()->title);
    }

    public function test_account_emails_use_ukrainian_labels_and_public_reset_url(): void
    {
        $user = $this->user();
        $reset = (new ResetPassword('example-reset-token'))->toMail($user);
        $this->assertSame('Відновлення пароля — Рідна Віра', $reset->subject);
        $this->assertSame(route('password.reset', ['token' => 'example-reset-token', 'email' => $user->email]), $reset->actionUrl);
        $this->assertStringNotContainsString('/admin', $reset->actionUrl);
        $verification = (new VerifyEmail)->toMail($user);
        $this->assertSame('Підтвердження пошти — Рідна Віра', $verification->subject);
        $this->assertStringContainsString('/kabinet/pidtverdzhennia-poshty/', $verification->actionUrl);
    }
}
