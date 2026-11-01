<?php

use App\Actions\StorePromoPhoto;
use App\Models\Promo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake(Promo::PHOTO_DISK);
});

/**
 * The bytes of an image of the given size in the given format. The top-left corner is red, the rest blue.
 */
function photoBytes(int $width, int $height, string $format = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 0, 0, 255));
    imagefilledrectangle($image, 0, 0, intdiv($width, 3), intdiv($height, 3), (int) imagecolorallocate($image, 255, 0, 0));

    ob_start();
    match ($format) {
        'jpeg' => imagejpeg($image, null, 95),
        'png' => imagepng($image),
        'webp' => imagewebp($image),
    };

    return (string) ob_get_clean();
}

/**
 * A JPEG with an EXIF segment that holds an Orientation and a secret, as a phone camera would write it.
 */
function jpegWithExif(string $jpeg, int $orientation): string
{
    $app1 = "Exif\0\0"."II*\0".pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0).'GPS-SECRET';

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

function storePhoto(string $bytes, string $clientName = 'photo.jpg'): string
{
    return app(StorePromoPhoto::class)->handle(UploadedFile::fake()->createWithContent($clientName, $bytes));
}

function storedPhoto(string $path): string
{
    return (string) Storage::disk(Promo::PHOTO_DISK)->get($path);
}

function isRedAt(GdImage $image, int $x, int $y): bool
{
    $colour = imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));

    return $colour['red'] > 200 && $colour['blue'] < 80;
}

it('scales the longest edge down to 1600px and never scales up', function (int $width, int $height, int $newWidth, int $newHeight) {
    $size = getimagesizefromstring(storedPhoto(storePhoto(photoBytes($width, $height))));

    expect([$size[0], $size[1]])->toBe([$newWidth, $newHeight]);
})->with([
    'landscape' => [3000, 2000, 1600, 1067],
    'portrait' => [2000, 3000, 1067, 1600],
    'already small' => [800, 600, 800, 600],
    'exactly the limit' => [1600, 900, 1600, 900],
    'a thin strip keeps a row of pixels' => [5000, 1, 1600, 1],
]);

it('always writes a JPEG at a generated path, whatever the input and its name', function (string $format, string $clientName) {
    $path = storePhoto(photoBytes(40, 30, $format), $clientName);

    expect($path)->toMatch('#^promos/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$#')
        ->and(getimagesizefromstring(storedPhoto($path))['mime'])->toBe('image/jpeg')
        ->and(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([$path]);
})->with([
    'jpeg' => ['jpeg', 'holiday.jpg'],
    'png' => ['png', 'screenshot.png'],
    'webp' => ['webp', 'folder.webp'],
    'a name that tries to leave the folder' => ['png', '../../evil.php'],
]);

it('reads the injected EXIF as an Orientation before processing', function () {
    $upload = UploadedFile::fake()->createWithContent('photo.jpg', jpegWithExif(photoBytes(40, 30), 6));

    expect(exif_read_data($upload->getPathname())['Orientation'])->toBe(6);
});

it('strips EXIF and other metadata from the output', function (int $orientation) {
    $bytes = storedPhoto(storePhoto(jpegWithExif(photoBytes(300, 200), $orientation)));
    $exif = @exif_read_data(UploadedFile::fake()->createWithContent('check.jpg', $bytes)->getPathname());

    expect($bytes)->not->toContain('Exif')
        ->and($bytes)->not->toContain('GPS-SECRET')
        ->and(is_array($exif) ? ($exif['Orientation'] ?? null) : null)->toBeNull();
})->with(['upright' => 1, 'turned' => 6]);

it('applies the EXIF orientation', function (int $orientation, int $width, int $height, array $redCorner) {
    $image = imagecreatefromstring(storedPhoto(storePhoto(jpegWithExif(photoBytes(300, 200), $orientation))));
    $corners = [
        'top left' => isRedAt($image, 5, 5),
        'top right' => isRedAt($image, $width - 6, 5),
        'bottom left' => isRedAt($image, 5, $height - 6),
        'bottom right' => isRedAt($image, $width - 6, $height - 6),
    ];

    expect([imagesx($image), imagesy($image)])->toBe([$width, $height])
        ->and(array_keys(array_filter($corners)))->toBe($redCorner);
})->with([
    '1, upright' => [1, 300, 200, ['top left']],
    '2, mirrored' => [2, 300, 200, ['top right']],
    '3, upside down' => [3, 300, 200, ['bottom right']],
    '4, flipped' => [4, 300, 200, ['bottom left']],
    '5, transposed' => [5, 200, 300, ['top left']],
    '6, turned clockwise' => [6, 200, 300, ['top right']],
    '7, transversed' => [7, 200, 300, ['bottom right']],
    '8, turned counter-clockwise' => [8, 200, 300, ['bottom left']],
]);

it('keeps the 1600px limit on a turned photo', function () {
    $size = getimagesizefromstring(storedPhoto(storePhoto(jpegWithExif(photoBytes(3000, 2000), 6))));

    expect([$size[0], $size[1]])->toBe([1067, 1600]);
});

it('leaves a photo as it is for an orientation outside 1 to 8', function () {
    $size = getimagesizefromstring(storedPhoto(storePhoto(jpegWithExif(photoBytes(300, 200), 9))));

    expect([$size[0], $size[1]])->toBe([300, 200]);
});

it('puts transparent areas on white', function () {
    $source = imagecreatetruecolor(40, 40);
    imagesavealpha($source, true);
    imagealphablending($source, false);
    imagefill($source, 0, 0, (int) imagecolorallocatealpha($source, 0, 0, 0, 127));

    ob_start();
    imagepng($source);
    $image = imagecreatefromstring(storedPhoto(storePhoto((string) ob_get_clean(), 'clear.png')));
    $colour = imagecolorsforindex($image, (int) imagecolorat($image, 20, 20));

    expect([$colour['red'], $colour['green'], $colour['blue']])->each->toBeGreaterThan(250);
});

it('rejects bytes that start like a JPEG but do not decode, and writes nothing', function () {
    try {
        storePhoto("\xFF\xD8\xFF\xE0garbage");
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['photo' => ["That file can't be read as a photo."]]);
    }

    expect(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([]);
});

it('rejects a valid JPEG header with nothing behind it, and writes nothing', function () {
    // The start-of-frame marker gets past the header check, so GD itself has to fail on the rest.
    $header = "\xFF\xD8\xFF\xC0\x00\x11\x08\x00\x10\x00\x10\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01";

    expect(getimagesizefromstring($header)[0])->toBe(16)
        ->and(fn () => storePhoto($header))->toThrow(ValidationException::class)
        ->and(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([]);
});

it('rejects a file type outside JPEG, PNG and WebP, and writes nothing', function () {
    ob_start();
    imagegif(imagecreatetruecolor(10, 10));
    $gif = (string) ob_get_clean();

    expect(fn () => storePhoto($gif, 'moving.gif'))->toThrow(ValidationException::class)
        ->and(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([]);
});

it('rejects a header over the pixel limit without decoding it', function () {
    // Only the signature and IHDR: with no image data behind them, this message can only come from the size check.
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 20000, 20000)."\x08\x06\x00\x00\x00\x00\x00\x00\x00";

    try {
        storePhoto($png, 'bomb.png');
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['photo' => ['That photo is too big. Use one under 50 megapixels.']]);
    }

    expect(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([]);
});

it('allows up to 50 megapixels by the header', function () {
    // Under the limit, so it gets as far as decoding, which then fails for the missing image data.
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 7071, 7071)."\x08\x06\x00\x00\x00\x00\x00\x00\x00";

    try {
        storePhoto($png, 'large.png');
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['photo' => ["That file can't be read as a photo."]]);
    }
});

it('throws when the disk refuses the write, rather than returning a dead path', function () {
    $bytes = photoBytes(40, 30);

    Storage::shouldReceive('disk')->with(Promo::PHOTO_DISK)->andReturn(Mockery::mock(['put' => false]));

    expect(fn () => storePhoto($bytes))->toThrow(RuntimeException::class);
});

it('names the limits', function () {
    expect(StorePromoPhoto::MAX_PIXELS)->toBe(50_000_000)
        ->and(StorePromoPhoto::MAX_EDGE)->toBe(1600);
});
