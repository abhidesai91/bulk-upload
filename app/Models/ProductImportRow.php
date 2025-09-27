<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_import_id','row_number','sku','status','message'
    ];

    public function import()
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}

