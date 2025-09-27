<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

     protected $fillable = [
        'name',
        'sku',
        'price',
        'description',
        'primary_image_id',
    ];

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function primaryImage()
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }
}
