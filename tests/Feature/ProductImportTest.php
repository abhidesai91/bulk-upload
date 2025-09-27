<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_upsert_counts_and_updates(): void
    {
        // Given existing product B2
        Product::create(['sku' => 'B2', 'name' => 'OldBravo', 'price' => 9.99]);

        // Create CSV content with duplicates + invalid
        $csv = <<<CSV
sku,name,price
A1,Alpha,10.00
A1,AlphaDup,12.00
B2,Bravo,20.00
,NoSku,5.00
B2,BravoUpdated,22.00
CSV;

        // Write to temp and create UploadedFile
        $tmpPath = storage_path('app/testing_products.csv');
        if (!is_dir(dirname($tmpPath))) { mkdir(dirname($tmpPath), 0777, true); }
        file_put_contents($tmpPath, $csv);
        $file = new UploadedFile($tmpPath, 'products.csv', 'text/csv', null, true);

        // When
        $res = $this->post(route('import.csv'), ['csv_file' => $file]);

        // Then
        $res->assertRedirect();
        $res = $this->followRedirects($res);
        $res->assertSee('Total:');

        // Assert DB changes: A1 created, B2 updated, duplicates and invalid counted
        $a1 = Product::where('sku', 'A1')->first();
        $this->assertNotNull($a1);
        $this->assertSame('Alpha', $a1->name);
        $this->assertEquals(10.00, (float) $a1->price);

        $b2 = Product::where('sku', 'B2')->first();
        $this->assertSame('Bravo', $b2->name); // second B2 row is duplicate and ignored
        $this->assertEquals(20.00, (float) $b2->price);
    }
}

