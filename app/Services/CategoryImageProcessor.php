<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class CategoryImageProcessor
{
    private const TARGET_SIZE = 512;

    private const QUALITY = 85;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Process uploaded image: EXIF-orient, center-cover crop to square, encode as WebP.
     * Stores under the given directory on the media disk and returns the relative path.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $image = $this->manager->decodePath($file->getRealPath());
        $image->orient();
        $image->cover(self::TARGET_SIZE, self::TARGET_SIZE);

        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        $path = rtrim($directory, '/').'/'.Str::uuid()->toString().'.webp';
        Storage::disk(config('filesystems.media_disk', 'public'))->put($path, (string) $encoded);

        return $path;
    }
}
