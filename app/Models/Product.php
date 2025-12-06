<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection|ProductImage[] $images
 */

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    protected $appends = ['images_urls'];

    public function getImagesUrlsAttribute()
    {
        return $this->images->map->url->all();
    }
}
