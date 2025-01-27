<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 
        'description', 
        'image', 
        'link', 
        'text_button'
    ];

    protected $dates = ['created_at', 'updated_at'];
}