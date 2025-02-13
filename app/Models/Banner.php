<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Banner extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'text_button'
    ];

    protected $dates = ['created_at', 'updated_at'];
}
