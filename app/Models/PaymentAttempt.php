<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PaymentAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_id',
        'status',
        'response_data',
        'error_message'
    ];

    protected $casts = [
        'response_data' => 'array',
        'status' => PaymentStatus::class
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
