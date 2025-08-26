<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'direction',   // credit | debit
        'amount',      // رقم صحيح (أصغر وحدة)
        'currency',    // SYP
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    // علاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // سكوبات مفيدة لاحقاً للبحث والترتيب
    public function scopeSearch($q, ?string $term)
    {
        if (!filled($term)) return $q;
        $like = '%'.trim($term).'%';
        return $q->where(function ($qq) use ($like) {
            $qq->where('title', 'like', $like)
               ->orWhere('description', 'like', $like)
               ->orWhere('type', 'like', $like);
        });
    }

    public function scopeOrderByCreated($q, string $dir = 'desc')
    {
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        return $q->orderBy('created_at', $dir)->orderBy('id', $dir);
    }

    // قيمة بالموجب/السالب (اختيارية للعرض فقط)
    protected $appends = ['signed_amount'];

    public function getSignedAmountAttribute(): int
    {
        return $this->direction === 'debit' ? -abs((int)$this->amount) : abs((int)$this->amount);
    }
}
