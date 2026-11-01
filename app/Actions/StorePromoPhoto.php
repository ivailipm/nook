<?php

namespace App\Actions;

use App\Models\Promo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Writes an uploaded photo to the promo disk as a fresh JPEG: at most 1600px on its longest edge, the right way up,
 * transparency on white, and without the EXIF and other metadata of the original.
 */
class StorePromoPhoto
{
    public const int MAX_PIXELS = 50_000_000;

    public const int MAX_EDGE = 1600;

    public const int JPEG_QUALITY = 82;

    public function handle(UploadedFile $photo): string
    {
        $file = $photo->getPathname();

        // The header alone gives the size, so an oversized image is turned away before GD allocates anything for it.
        $header = @getimagesize($file);

        if ($header === false) {
            throw $this->unreadable();
        }

        [$width, $height, $type] = $header;

        if ($width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => 'That photo is too big. Use one under 50 megapixels.']);
        }

        ini_set('memory_limit', '512M');

        $source = $this->decode($file, $type);

        if ($source === false) {
            throw $this->unreadable();
        }

        $image = $this->scaleOntoWhite($source);
        unset($source);

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->orient($image, $this->orientation($file));
        }

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();

        $path = 'promos/'.Str::uuid().'.jpg';

        if (! Storage::disk(Promo::PHOTO_DISK)->put($path, $jpeg)) {
            throw new RuntimeException("Could not write the promo photo to {$path}.");
        }

        return $path;
    }

    /**
     * GD warns on bad data, and Laravel turns warnings into exceptions, hence the @.
     */
    private function decode(string $file, int $type): GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => false,
        };
    }

    /**
     * A copy with its longest edge at most MAX_EDGE, never scaled up, with transparent areas on white.
     */
    private function scaleOntoWhite(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        // A very thin image still needs a row or a column of pixels.
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $image = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $image;
    }

    private function orientation(string $file): int
    {
        $exif = @exif_read_data($file);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /**
     * Applies an EXIF orientation. Flips work in place, and imagerotate turns counter-clockwise.
     */
    private function orient(GdImage $image, int $orientation): GdImage
    {
        match ($orientation) {
            2, 5, 7 => imageflip($image, IMG_FLIP_HORIZONTAL),
            4 => imageflip($image, IMG_FLIP_VERTICAL),
            default => null,
        };

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            5, 8 => imagerotate($image, 90, 0),
            6, 7 => imagerotate($image, -90, 0),
            default => $image,
        };
    }

    private function unreadable(): ValidationException
    {
        return ValidationException::withMessages(['photo' => "That file can't be read as a photo."]);
    }
}
