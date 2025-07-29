<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationPreference extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'user_id',
        'channel',
        'type',
        'enabled'
    ];

    protected $casts = [
        'enabled' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

class CouponNotification extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'user_id',
        'coupon_id',
        'type',
        'channel',
        'status',
        'data',
        'scheduled_at',
        'sent_at',
        'clicked_at',
        'failure_reason'
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'clicked_at' => 'datetime'
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('scheduled_at', '<=', now())
                    ->where('status', 'pending');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    // Métodos
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason
        ]);
    }

    public function markAsClicked(): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now()
        ]);
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at && $this->scheduled_at <= now() && $this->status === 'pending';
    }
}