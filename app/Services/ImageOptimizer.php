<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downscales and compresses uploaded images (via GD) before they are persisted, so full-resolution
 * camera photos never reach storage. Mirrors the mobile app's "cap ~1080px longest edge, compress
 * before saving" rule (architecture.md §10.3). Output is always a flattened JPEG.
 */
class ImageOptimizer
{
    /**
     * Optimize an uploaded image and store it on the given disk, returning the stored path.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $binary = $this->optimize($file);

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';

        Storage::disk($disk)->put($path, $binary);

        return $path;
    }

    /**
     * Decode, orient, downscale, and re-encode the upload, returning the JPEG binary.
     */
    private function optimize(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        $source = imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a decodable image.');
        }

        $source = $this->applyExifOrientation($source, $file);
        $resized = $this->downscale($source);
        imagedestroy($source);

        ob_start();
        imagejpeg($resized, null, $this->jpegQuality());
        $binary = (string) ob_get_clean();
        imagedestroy($resized);

        return $binary;
    }

    /**
     * Resize onto a fresh opaque (white-backed) canvas so the longest edge is at most `max_edge`,
     * flattening any transparency for JPEG output. Images already within bounds are only flattened.
     */
    private function downscale(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $scale = min(1.0, $this->maxEdge() / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white === false ? 0 : $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /**
     * Apply the JPEG EXIF orientation tag so rotated phone photos are stored upright. Non-JPEG
     * uploads and environments without the exif extension are left untouched.
     */
    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function maxEdge(): int
    {
        return max(1, (int) config('recipes.images.max_edge', 1080));
    }

    private function jpegQuality(): int
    {
        return min(100, max(1, (int) config('recipes.images.jpeg_quality', 85)));
    }
}
