<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'status',
        'slug',
        'created_by',
        'updated_by'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'blog_product');
    }
}
