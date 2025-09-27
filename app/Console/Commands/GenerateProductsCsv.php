<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateProductsCsv extends Command
{
    protected $signature = 'products:csv {--count=10000} {--path=}';
    protected $description = 'Generate a large CSV of products for import (sku,name,price)';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $path = $this->option('path') ?: storage_path('app/products_'.$count.'.csv');
        $dir = dirname($path);
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }

        $fh = fopen($path, 'w');
        if (!$fh) { $this->error('Cannot open file: '.$path); return self::FAILURE; }

        fputcsv($fh, ['sku','name','price','image']);
        for ($i = 1; $i <= $count; $i++) {
            $sku = 'SKU'.str_pad((string)$i, 6, '0', STR_PAD_LEFT);
            $name = 'Product '.$i;
            $price = number_format(mt_rand(100, 50000)/100, 2, '.', '');
            fputcsv($fh, [$sku, $name, $price, '']);
            if ($i % 1000 === 0) { $this->info("Written $i rows..."); }
        }
        fclose($fh);
        $this->info('CSV generated at: '.$path);
        return self::SUCCESS;
    }
}

