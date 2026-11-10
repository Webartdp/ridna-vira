<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active !== false && in_array($this->roleName(), ['admin', 'editor', 'shop_manager'], true);
    }

    public static function roles(): array
    {
        return ['admin' => 'Адміністратор', 'editor' => 'Редактор', 'shop_manager' => 'Менеджер крамниці', 'user' => 'Користувач'];
    }

    public function roleName(): string
    {
        return $this->role ?? ($this->is_admin ? 'admin' : 'user');
    }

    public function canManage(string $area): bool
    {
        return $this->is_active !== false && ($this->roleName() === 'admin' || match ($area) {
            'content' => $this->roleName() === 'editor', 'shop' => $this->roleName() === 'shop_manager', default => false,
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->isDirty('role')) {
                $user->is_admin = $user->role === 'admin';
            } elseif ($user->isDirty('is_admin')) {
                $user->role = $user->is_admin ? 'admin' : ($user->role === 'admin' ? 'user' : ($user->role ?? 'user'));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
