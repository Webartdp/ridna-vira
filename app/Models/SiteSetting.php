<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    protected $guarded = ['id'];

    public static function read(string $key, mixed $fallback = null): mixed
    {
        $request = request();
        if (! $request->attributes->has('site.settings')) {
            $request->attributes->set('site.settings', Schema::hasTable('site_settings') ? self::pluck('value', 'key')->all() : []);
        }
        $value = $request->attributes->get('site.settings')[$key] ?? null;

        return $value === null ? $fallback : ($key === 'wayforpay_secret' ? Crypt::decryptString($value) : $value);
    }

    public static function write(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $key === 'wayforpay_secret' && $value !== null ? Crypt::encryptString($value) : $value]);
        request()->attributes->remove('site.settings');
    }
}
