<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title','description','image_path','link_url',
        'is_active','sort_order','views_count','starts_at','ends_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];
}
