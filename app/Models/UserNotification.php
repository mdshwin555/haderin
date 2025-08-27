<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id','title','body','type','icon','data','read_at'
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    protected $appends = ['is_read'];

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
