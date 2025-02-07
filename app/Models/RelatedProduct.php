<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RelatedProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'related_product_id',
        'relationship_type', // 'similar', 'complementary', 'same_category'
        'score', // Para ordenar por relevancia
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function relatedProduct()
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
