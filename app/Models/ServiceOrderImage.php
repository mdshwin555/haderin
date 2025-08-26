<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceOrderImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_id',
        'path',
        'original_name',
        'size',
    ];

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    // مخرجات جاهزة للـ API
    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        // يفترض أنك عامل storage:link
        return $this->path ? asset('storage/'.$this->path) : null;
    }
}
