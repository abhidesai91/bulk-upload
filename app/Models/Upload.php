<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'path',
        'checksum',
        'status',
    ];

    
    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
