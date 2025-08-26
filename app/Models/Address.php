<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',        // home|work|other
        'title',
        'details',
        'street',
        'floor',
        'city',
        'contact_phone',
        'lat',
        'lng',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
