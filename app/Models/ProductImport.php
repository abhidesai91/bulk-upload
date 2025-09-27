<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path','status','total','imported','updated','invalid','duplicates','error','started_at','finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(ProductImportRow::class);
    }
}

