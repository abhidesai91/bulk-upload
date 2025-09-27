<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as Img;


class UploadController extends Controller
{
    public function showForm() {
        return view('upload');
    }

    public function chunk(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'index' => 'required|integer|min:0',
            'file' => 'required|file',
            'chunk_hash' => 'nullable|string',
            'total' => 'nullable|integer|min:1',
        ]);

        $name = $request->input('name');
        $index = (int) $request->input('index');
        $chunk = $request->file('file');
        $chunkHash = $request->input('chunk_hash');

        $dir = "chunks/$name";
        $path = "$dir/$index.part";
        if (!Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        if (Storage::exists($path)) {
            return response()->json(['status' => 'exists']);
        }

        $content = @file_get_contents($chunk->getRealPath() ?: $chunk->getPathname());
        if ($chunkHash) {
            $serverHash = hash('sha256', $content);
            if (!hash_equals($chunkHash, $serverHash)) {
                return response()->json(['status' => 'checksum_mismatch'], 422);
            }
        }

        // Write atomically
        $tmp = "$path.tmp";
        Storage::put($tmp, $content);
        Storage::move($tmp, $path);

        return response()->json(['status' => 'stored']);
    }

    public function complete(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'total' => 'required|integer|min:1',
            'checksum' => 'required|string',
        ]);

        $name = $request->input('name');
        $total = (int) $request->input('total');
        $checksum = $request->input('checksum');

        $dir = "chunks/$name";
        if (!Storage::exists($dir)) {
            return response()->json(['status' => 'missing_chunks_dir'], 422);
        }

        // Verify all chunks exist
        $missing = [];
        for ($i = 0; $i < $total; $i++) {
            if (!Storage::exists("$dir/$i.part")) {
                $missing[] = $i;
            }
        }
        if (!empty($missing)) {
            return response()->json(['status' => 'missing_chunks', 'missing' => $missing], 422);
        }

        // Merge with lock
        $finalPath = "uploads/images/$name";
        $finalAbs = storage_path("app/$finalPath");
        if (!is_dir(dirname($finalAbs))) {
            mkdir(dirname($finalAbs), 0777, true);
        }

        $fp = fopen($finalAbs . '.tmp', 'c+');
        if (!$fp) {
            return response()->json(['status' => 'merge_open_failed'], 500);
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                return response()->json(['status' => 'merge_lock_failed'], 500);
            }
            for ($i = 0; $i < $total; $i++) {
                $chunkContent = Storage::get("$dir/$i.part");
                fwrite($fp, $chunkContent);
            }
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (\Throwable $e) {
            @fclose($fp);
            return response()->json(['status' => 'merge_failed'], 500);
        }

        // Move tmp to final atomically
        rename($finalAbs . '.tmp', $finalAbs);

        // Verify overall checksum
        $serverChecksum = hash_file('sha256', $finalAbs);
        if (!hash_equals($checksum, $serverChecksum)) {
            @unlink($finalAbs);
            return response()->json(['status' => 'checksum_mismatch'], 422);
        }

        // Clean up chunks
        $files = Storage::files($dir);
        foreach ($files as $f) { Storage::delete($f); }
        Storage::deleteDirectory($dir);

        // Record upload
        $upload = Upload::create([
            'type' => 'image',
            'path' => $finalPath,
            'checksum' => $serverChecksum,
            'status' => 'completed',
        ]);

        // Generate variants respecting aspect ratio if a driver is available
        $original = $finalAbs;
        $sizes = [256, 512, 1024];
        $variants = [];
        $driver = null;
        if (extension_loaded('imagick')) { $driver = 'imagick'; }
        elseif (extension_loaded('gd')) { $driver = 'gd'; }

        if ($driver) {
            try {
                foreach ($sizes as $size) {
                    $variantPath = "uploads/images/{$size}_$name";
                    $pipeline = $driver === 'imagick' ? Img::imagick() : Img::gd();
                    $image = $pipeline->read($original)->scale(width: $size);
                    $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
                    Storage::put($variantPath, $image->encodeByExtension($ext, quality: 75));
                    $variants[$size] = $variantPath;
                }
            } catch (\Throwable $t) {
                // Fallback: keep variants empty if processing fails
            }
        }

        $imageRec = Image::create([
            'upload_id' => $upload->id,
            'original_path' => $finalPath,
            'size_256' => $variants[256] ?? null,
            'size_512' => $variants[512] ?? null,
            'size_1024' => $variants[1024] ?? null,
        ]);

        $variantsGenerated = !empty($variants);
        return response()->json(['status' => 'completed', 'upload_id' => $upload->id, 'image_id' => $imageRec->id, 'variants_generated' => $variantsGenerated]);
    }
}