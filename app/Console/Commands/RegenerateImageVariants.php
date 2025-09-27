<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as Img;

class RegenerateImageVariants extends Command
{
    protected $signature = 'images:regenerate {--only-missing : Only regenerate when a variant path is null} {--force : Overwrite existing variants if present}';
    protected $description = 'Regenerate 256/512/1024 image variants for existing Image records';

    public function handle(): int
    {
        $driver = null;
        if (extension_loaded('imagick')) { $driver = 'imagick'; }
        elseif (extension_loaded('gd')) { $driver = 'gd'; }

        if (!$driver) {
            $this->error('No image driver available. Enable GD or Imagick in PHP to generate variants.');
            return self::FAILURE;
        }

        $onlyMissing = (bool)$this->option('only-missing');
        $force = (bool)$this->option('force');
        $sizes = [256, 512, 1024];

        $query = Image::query();
        if ($onlyMissing) {
            $query->where(function($q){
                $q->whereNull('size_256')->orWhereNull('size_512')->orWhereNull('size_1024');
            });
        }

        $count = 0;
        $this->info('Starting regeneration...');

        $query->chunk(100, function($images) use (&$count, $driver, $sizes, $force) {
            foreach ($images as $imgRec) {
                $originalRel = $imgRec->original_path;
                $originalAbs = storage_path('app/'.$originalRel);
                if (!is_file($originalAbs)) {
                    $this->warn("Missing original: {$originalRel}");
                    continue;
                }
                $name = basename($originalRel);
                $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
                $pipeline = $driver === 'imagick' ? Img::imagick() : Img::gd();
                try {
                    foreach ($sizes as $size) {
                        $col = 'size_'.$size;
                        $variantPath = $imgRec->{$col} ?: "uploads/images/{$size}_{$name}";
                        if (!$force && $imgRec->{$col}) {
                            // Already present, skip
                            continue;
                        }
                        $image = $pipeline->read($originalAbs)->scale(width: $size);
                        Storage::put($variantPath, $image->encodeByExtension($ext, quality: 75));
                        $imgRec->{$col} = $variantPath;
                    }
                    $imgRec->save();
                    $count++;
                } catch (\Throwable $e) {
                    $this->warn("Failed {$originalRel}: ".$e->getMessage());
                }
            }
        });

        $this->info("Done. Updated {$count} records.");
        return self::SUCCESS;
    }
}

