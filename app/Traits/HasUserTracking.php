<?php
// app/Traits/HasUserTracking.php

namespace App\Traits;

use App\src\Domain\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasUserTracking
{
    protected static function bootHasUserTracking()
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}