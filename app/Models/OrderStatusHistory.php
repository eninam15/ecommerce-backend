<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Enums\OrderStatus;


class OrderStatusHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'status',
        'comment',
        'created_by'
    ];

    protected $casts = [
        'status' => OrderStatus::class
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
