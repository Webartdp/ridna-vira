<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteMcpToken extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
