<?php

use App\Intake\PhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A JPEG with an EXIF block carrying the given orientation.
 */
function orientedJpeg(int $orientation, int $width = 320, int $height = 240): UploadedFile
{
    $jpeg = UploadedFile::fake()->image('phone.jpg', $width, $height);
    $bytes = (string) file_get_contents($jpeg->getPathname());
    // APP1 marker, "Exif\0\0", a big-endian TIFF header, one IFD with one entry: tag 0x0112 (Orientation), SHORT, count 1.
    $tiff = 'MM'."\x00\x2A"."\x00\x00\x00\x08"."\x00\x01"."\x01\x12"."\x00\x03"."\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"."\x00\x00\x00\x00";
    $app1 = 'Exif'."\x00\x00".$tiff;
    file_put_contents($jpeg->getPathname(), substr($bytes, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($bytes, 2));

    return $jpeg;
}

it('strips metadata from uploaded photos by re-encoding them', function () {
    $jpeg = orientedJpeg(1);
    expect(exif_read_data($jpeg->getPathname())['Orientation'] ?? null)->toBe(1);

    $stored = (new PhotoStore)->store($jpeg, 'abc', 1);
    $path = Storage::disk('local')->path($stored['path']);

    expect(strpos((string) file_get_contents($path), 'Exif'))->toBeFalse()
        ->and(getimagesize($path)[2])->toBe(IMAGETYPE_JPEG)
        ->and($stored)->toBe(['number' => 1, 'path' => 'requests/abc/photo-1.jpg', 'mime_type' => 'image/jpeg']);
});

it('applies the EXIF orientation before dropping it, so phone photos come out upright', function (int $orientation, int $width, int $height) {
    $stored = (new PhotoStore)->store(orientedJpeg($orientation, 320, 240), 'abc', 1);

    [$storedWidth, $storedHeight] = getimagesize(Storage::disk('local')->path($stored['path']));

    expect([$storedWidth, $storedHeight])->toBe([$width, $height]);
})->with([
    'upright' => [1, 320, 240],
    'upside down' => [3, 320, 240],
    'rotated 90 clockwise' => [6, 240, 320],
    'rotated 90 counterclockwise' => [8, 240, 320],
]);

it('lays transparent PNG pixels over white instead of black', function () {
    $png = UploadedFile::fake()->create('clear.png', 0, 'image/png');
    $image = imagecreatetruecolor(4, 4);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagepng($image, $png->getPathname());

    $stored = (new PhotoStore)->store($png, 'abc', 1);
    $result = imagecreatefromjpeg(Storage::disk('local')->path($stored['path']));
    $pixel = imagecolorsforindex($result, imagecolorat($result, 2, 2));

    expect($pixel['red'])->toBeGreaterThan(240)
        ->and($pixel['green'])->toBeGreaterThan(240)
        ->and($pixel['blue'])->toBeGreaterThan(240);
});

it('reports a corrupt file instead of crashing', function () {
    $jpeg = UploadedFile::fake()->image('broken.jpg', 64, 64);
    file_put_contents($jpeg->getPathname(), substr((string) file_get_contents($jpeg->getPathname()), 0, 40));

    expect(fn () => (new PhotoStore)->store($jpeg, 'abc', 1))->toThrow(RuntimeException::class, 'could not be read');
});
