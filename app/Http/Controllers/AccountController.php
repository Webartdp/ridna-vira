<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private function normalize(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    private function uniqueEmail(?int $ignore = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($ignore) {
            if (User::whereRaw('LOWER(email) = ?', [$value])->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
                $fail('Цю електронну пошту вже використовують.');
            }
        };
    }

    public function register(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:190', $this->uniqueEmail()], 'password' => ['required', 'confirmed', 'string', 'max:128', PasswordRule::min(12)->letters()->numbers()]]);
        $user = new User($data);
        $user->forceFill(['role' => 'user', 'is_admin' => false, 'is_active' => true])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $sent = $this->notifyVerification($user, true);

        return redirect()->route('account.index')->with('status', $sent ? 'Обліковий запис створено. Підтвердьте пошту за посиланням у листі.' : 'Обліковий запис створено. Лист підтвердження поки не вдалося надіслати. Спробуйте повторити запит із кабінету пізніше.');
    }

    public function login(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:128', 'remember' => 'sometimes|boolean']);
        $candidate = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();
        // Auth::attempt performs password validation and session authentication with the current active flag.
        if (! Auth::attempt(['email' => $candidate?->email ?? $data['email'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Невірна пошта або пароль, чи обліковий запис неактивний.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function index(Request $request)
    {
        return view('account.dashboard', ['orders' => $request->user()->orders()->latest()->paginate(12)]);
    }

    public function profile(Request $request)
    {
        $this->normalize($request);
        $user = $request->user();
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:190', $this->uniqueEmail($user->id)], 'phone' => 'nullable|string|max:30', 'delivery_address' => 'nullable|string|max:1000', 'current_password' => 'required|current_password:web', 'password' => ['nullable', 'confirmed', 'string', 'max:128', PasswordRule::min(12)->letters()->numbers()]]);
        $emailChanged = $data['email'] !== $user->email;
        if ($emailChanged) {
            Password::broker()->deleteToken($user);
            $user->email_verified_at = null;
        }
        $user->forceFill(Arr::only($data, ['name', 'email', 'phone', 'delivery_address']));
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($user->isDirty(['password', 'email'])) {
            $user->remember_token = Str::random(60);
        }
        $user->save();
        $request->session()->put('password_hash_web', $user->password);
        if ($emailChanged) {
            $this->notifyVerification($user);
        }

        return back()->with('status', 'Профіль збережено.'.($emailChanged ? ' Підтвердьте нову електронну пошту.' : ''));
    }

    public function forgot(Request $request)
    {
        $this->normalize($request);
        $request->validate(['email' => 'required|email|max:190']);
        $user = User::whereRaw('LOWER(email) = ?', [$request->input('email')])->where('is_active', true)->first();
        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email, 'is_active' => true]);
            } catch (\Throwable $error) {
                Log::warning('Account reset notification failed.', ['exception' => $error::class]);
            }
        }

        return back()->with('status', 'Якщо ця адреса належить активному обліковому запису, на неї надіслано посилання для відновлення пароля.');
    }

    public function reset(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', 'string', 'max:128', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset([...$data, 'is_active' => true], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Посилання недійсне або прострочене. Запросіть новий лист.']);
        }

        return redirect()->route('login')->with('status', 'Пароль змінено. Увійдіть із новим паролем.');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('account.index')->with('status', 'Електронну пошту підтверджено.');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('status', 'Електронну пошту вже підтверджено.');
        }
        $sent = $this->notifyVerification($request->user());

        return back()->with('status', $sent ? 'Лист підтвердження надіслано. Перевірте пошту.' : 'Лист поки не вдалося надіслати. Спробуйте пізніше.');
    }

    private function notifyVerification(User $user, bool $registered = false): bool
    {
        try {
            $registered ? event(new Registered($user)) : $user->sendEmailVerificationNotification();

            return true;
        } catch (\Throwable $error) {
            Log::warning('Account verification notification failed.', ['exception' => $error::class]);

            return false;
        }
    }

    public function order(Request $request, string $reference)
    {
        $order = $request->user()->orders()->where('reference', $reference)->with('items')->firstOrFail();

        return response()->view('shop.order', compact('order'))->header('Referrer-Policy', 'no-referrer');
    }

    public function claim(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|size:64']);
        DB::transaction(function () use ($data, $request) {
            $order = Order::where('access_token', $data['token'])->lockForUpdate()->firstOrFail();
            abort_unless(($order->user_id === null || $order->user_id === $request->user()->id) && mb_strtolower($order->email) === mb_strtolower($request->user()->email), 404);
            $order->update(['user_id' => $request->user()->id]);
        });

        return redirect()->route('account.index')->with('status', 'Замовлення додано до вашого кабінету.');
    }
}
