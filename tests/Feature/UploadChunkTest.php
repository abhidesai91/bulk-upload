<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class UploadChunkTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunked_upload_with_checksum_and_variants(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available in test environment.');
        }
        // Create a small valid PNG in memory
        $manager = new ImageManager(new GdDriver());
        $img = $manager->create(20, 10)->fill('#ff0000');
        $png = $img->encodeByExtension('png');

        $name = 'test-image.png';
        $total = 2;
        $mid = intdiv(strlen($png), 2);
        $chunk0 = substr($png, 0, $mid);
        $chunk1 = substr($png, $mid);

        $checksum = hash('sha256', $png);

        // Send chunks
        $res1 = $this->post(route('upload.chunk'), [
            'name' => $name,
            'index' => 0,
            'file' => $this->fakeChunkUpload($chunk0, 'c0.bin'),
            'chunk_hash' => hash('sha256', $chunk0),
            'total' => $total,
        ]);
        $res1->assertOk();

        $res2 = $this->post(route('upload.chunk'), [
            'name' => $name,
            'index' => 1,
            'file' => $this->fakeChunkUpload($chunk1, 'c1.bin'),
            'chunk_hash' => hash('sha256', $chunk1),
            'total' => $total,
        ]);
        $res2->assertOk();

        // Complete
        $res3 = $this->post(route('upload.complete'), [
            'name' => $name,
            'total' => $total,
            'checksum' => $checksum,
        ]);
        $res3->assertOk();
        $data = $res3->json();
        $this->assertSame('completed', $data['status']);

        // Records
        $this->assertDatabaseCount('uploads', 1);
        $this->assertDatabaseCount('images', 1);

        $upload = Upload::first();
        $image = Image::first();
        $this->assertNotEmpty($upload->path);
        $this->assertNotEmpty($image->size_256);
        $this->assertNotEmpty($image->size_512);
        $this->assertNotEmpty($image->size_1024);
    }

    private function fakeChunkUpload(string $content, string $name)
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'chunk_');
        file_put_contents($tmpPath, $content);
        return new \Illuminate\Http\UploadedFile($tmpPath, $name, null, null, true);
    }
}

