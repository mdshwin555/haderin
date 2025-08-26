<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * الحقول المسموح تعبئتها.
     */
    protected $fillable = [
        // الملف الشخصي
        'full_name', 'phone', 'gender', 'city',

        // الدور والحالة
        'role', 'status',

        // الإحالات
        'referral_code', 'invited_by_code',

        // اكتمال الملف الشخصي
        'profile_completed_at',
    ];

    /**
     * الإخفاء عند التحويل لمصفوفة/JSON (ما عنا شي حاليًا).
     */
    protected $hidden = [];

    /**
     * التحويلات.
     */
    protected function casts(): array
    {
        return [
            'profile_completed_at' => 'datetime',
        ];
    }
    // app/Models/User.php
public function addresses()
{
    return $this->hasMany(\App\Models\Address::class);
}

// محفظة المستخدم (سطر واحد)
public function wallet()
{
    return $this->hasOne(\App\Models\Wallet::class);
}

// كل الحركات
public function walletTransactions()
{
    return $this->hasMany(\App\Models\WalletTransaction::class);
}


public function conversations()
{
    return $this->hasMany(\App\Models\Conversation::class, 'customer_id');
}
public function sentMessages()
{
    return $this->hasMany(\App\Models\Message::class, 'sender_id');
}

// مَن دعوتُهم: users.invited_by_code == this.referral_code
public function invitees()
{
    return $this->hasMany(\App\Models\User::class, 'invited_by_code', 'referral_code');
}

// مُعرّفي (إن وُجد): users.referral_code == this.invited_by_code
public function inviter()
{
    return $this->belongsTo(\App\Models\User::class, 'invited_by_code', 'referral_code');
}


public function serviceOrders()
{
    return $this->hasMany(\App\Models\ServiceOrder::class, 'customer_id');
}


protected static function booted()
{
    static::created(function (User $user) {
        // أنشئ محفظة افتراضياً إن ما كانت موجودة (unique على user_id يمنع التكرار)
        $user->wallet()->create([
            'balance'  => 0,
            'currency' => 'SYP',
        ]);
    });
}



}
