<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;



class Payment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'order_id',
        'payment_method',
        'provider',
        'status',
        'amount',
        'currency',
        'transaction_id',
        'metadata',
        'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'payment_method' => PaymentMethod::class,
        'status' => PaymentStatus::class
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
