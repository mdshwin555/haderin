<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'address_id',
        'category',
        'description',
        'payment_method',
        'cost',
        'status',
    ];

    // العلاقات
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function images()
    {
        return $this->hasMany(ServiceOrderImage::class);
    }
}
