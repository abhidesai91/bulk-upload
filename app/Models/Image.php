<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'original_path',
        'size_256',
        'size_512',
        'size_1024',
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
