<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_csv_and_updates_summary(): void
    {
        // Prepare CSV
        $csv = <<<CSV
sku,name,price
A1,Alpha,10.00
A1,AlphaDup,12.00
B2,Bravo,20.00
,NoSku,5.00
B2,BravoUpdated,22.00
CSV;
        $path = storage_path('app/testing_job_products.csv');
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
        file_put_contents($path, $csv);

        // Create import
        $import = ProductImport::create([
            'file_path' => 'testing_job_products.csv',
            'status' => 'pending',
        ]);

        // Run job directly
        (new ProcessProductImport($import->id))->handle();
        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertSame(5, (int)$import->total); // 5 data rows after header
        $this->assertSame(2, (int)$import->imported); // A1 and first B2 created
        $this->assertSame(0, (int)$import->updated); // later B2 row is treated as duplicate-in-file
        $this->assertSame(1, (int)$import->invalid); // missing sku
        $this->assertSame(2, (int)$import->duplicates); // A1 duplicate and B2 duplicate

        // DB state
        $this->assertNotNull(Product::where('sku', 'A1')->first());
        $this->assertNotNull(Product::where('sku', 'B2')->first());

        // Row logs should exist
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id, 'status' => 'imported']);
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id, 'status' => 'invalid']);
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id, 'status' => 'duplicate']);
    }
}

