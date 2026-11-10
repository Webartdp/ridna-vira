<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageSiteUsers
{
    public function save(?User $record, array $data): User
    {
        abort_unless(auth()->user()?->canManage('admin'), 403);
        $data = Validator::make($data, [
            'name' => 'required|string|max:150', 'email' => 'required|email|max:190',
            'role' => ['required', Rule::in(array_keys(User::roles()))], 'is_active' => 'required|boolean',
            'password' => [Rule::requiredIf(! $record), 'nullable', 'string', 'max:128', \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers()],
        ])->validate();

        return DB::transaction(function () use ($record, $data) {
            // Lock all administrators in a stable order before changing access.
            $administrators = User::where('is_admin', true)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless(User::lockForUpdate()->find(auth()->id())?->canManage('admin'), 403);
            $user = $record ? User::lockForUpdate()->findOrFail($record->id) : new User;
            if ($record && auth()->id() === $user->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'Власний доступ адміністратора не можна заблокувати або понизити.']);
            }
            if ($user->is_admin && $user->is_active && ($data['role'] !== 'admin' || ! $data['is_active']) && $administrators->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'На сайті має залишитися хоча б один активний адміністратор.']);
            }
            $before = ['role' => $user->roleName(), 'is_active' => $user->is_active];
            $email = mb_strtolower(trim($data['email']));
            if (User::whereRaw('LOWER(email) = ?', [$email])->when($user->exists, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
                throw ValidationException::withMessages(['email' => 'Цю електронну пошту вже використовують.']);
            }
            if ($user->exists && $email !== $user->email) {
                Password::broker()->deleteToken($user);
                $user->email_verified_at = null;
            }
            $user->forceFill([...Arr::only($data, ['name', 'role', 'is_active']), 'email' => $email]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            if ($user->isDirty(['password', 'email', 'role', 'is_active'])) {
                $user->remember_token = Str::random(60);
            }
            $user->save();
            DB::table('user_access_audits')->insert(['actor_id' => auth()->id(), 'user_id' => $user->id, 'before_state' => json_encode($before), 'after_state' => json_encode(['role' => $user->roleName(), 'is_active' => $user->is_active]), 'created_at' => now()]);

            return $user;
        }, 3);
    }
}
