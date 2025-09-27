<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Csv\Reader;
use Throwable;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $importId)
    {
    }

    public function handle(): void
    {
        $import = ProductImport::findOrFail($this->importId);
        $import->update(['status' => 'processing', 'started_at' => now()]);

        $summary = ['total'=>0,'imported'=>0,'updated'=>0,'invalid'=>0,'duplicates'=>0];
        $seen = [];

        try {
            $csv = Reader::createFromPath(storage_path('app/'.$import->file_path), 'r');
            $csv->setHeaderOffset(0);
            $rowNum = 1; // header is row 1; first data row will be 2
            foreach ($csv->getRecords() as $row) {
                $rowNum++;
                $summary['total']++;
                $sku = isset($row['sku']) ? trim((string) $row['sku']) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($sku === '' || $name === '') {
                    $summary['invalid']++;
                    ProductImportRow::create([
                        'product_import_id' => $import->id,
                        'row_number' => $rowNum,
                        'sku' => $sku ?: null,
                        'status' => 'invalid',
                        'message' => 'Missing sku or name',
                    ]);
                    continue;
                }
                if (in_array($sku, $seen, true)) {
                    $summary['duplicates']++;
                    ProductImportRow::create([
                        'product_import_id' => $import->id,
                        'row_number' => $rowNum,
                        'sku' => $sku,
                        'status' => 'duplicate',
                        'message' => 'Duplicate in file',
                    ]);
                    continue;
                }
                $seen[] = $sku;

                $payload = [
                    'name' => $name,
                    'price' => isset($row['price']) && $row['price'] !== '' ? (float)$row['price'] : null,
                ];

                $product = Product::updateOrCreate(['sku' => $sku], $payload);
                $status = $product->wasRecentlyCreated ? 'imported' : 'updated';
                $summary[$status]++;

                // Optional: link primary image if an 'image' column is provided
                if (!empty($row['image'])) {
                    $filename = basename(trim((string)$row['image']));
                    if ($filename !== '') {
                        $img = \App\Models\Image::where('original_path', 'like', "%/$filename")->latest()->first();
                        if ($img && $product->primary_image_id !== $img->id) {
                            $product->primary_image_id = $img->id;
                            $product->save();
                        }
                    }
                }

                ProductImportRow::create([
                    'product_import_id' => $import->id,
                    'row_number' => $rowNum,
                    'sku' => $sku,
                    'status' => $status,
                    'message' => null,
                ]);
            }

            $import->update(array_merge($summary, [
                'status' => 'completed',
                'finished_at' => now(),
            ]));
        } catch (Throwable $e) {
            $import->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e; // let the queue handle retries/logging
        }
    }
}

