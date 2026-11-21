<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MakerTelegramLink extends Model
{
    protected $fillable = ['user_id', 'chat_id', 'pairing_hash', 'pairing_expires_at'];

    protected $hidden = ['chat_id', 'pairing_hash'];

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
