<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Otp extends Model
{
    protected $table = 'otps';

    protected $fillable = [
        'phone', 'otp', 'used', 'expires_at', 'resend_count',
    ];

    protected $casts = [
        'used'       => 'boolean',
        'expires_at' => 'datetime',
    ];

    // <-- هذا هو السكوب المطلوب
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('used', false)
                 ->where('expires_at', '>', now());
    }
}
