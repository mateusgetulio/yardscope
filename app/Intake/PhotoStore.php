<?php

namespace App\Intake;

use ErrorException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class PhotoStore
{
    /**
     * Re-encodes the upload with GD, which drops EXIF and every other tag (including GPS) before
     * the file is kept or sent to a model. The EXIF orientation is applied first, because dropping
     * the tag without rotating would leave phone photos on their side.
     *
     * @return array{number: int, path: string, mime_type: string}
     */
    public function store(UploadedFile $upload, string $requestId, int $number): array
    {
        $bytes = $this->encode($this->upright($this->decode($upload), $upload));
        $path = "requests/{$requestId}/photo-{$number}.jpg";

        Storage::disk('local')->put($path, $bytes);

        return ['number' => $number, 'path' => $path, 'mime_type' => 'image/jpeg'];
    }

    private function decode(UploadedFile $upload): \GdImage
    {
        try {
            $image = match ($upload->getMimeType()) {
                'image/jpeg' => imagecreatefromjpeg($upload->getPathname()),
                'image/png' => imagecreatefrompng($upload->getPathname()),
                'image/webp' => imagecreatefromwebp($upload->getPathname()),
                default => false,
            };
        } catch (ErrorException $exception) {
            // GD warns on truncated or corrupt files; the framework turns the warning into an exception.
            throw new RuntimeException("Photo {$upload->getClientOriginalName()} could not be read.", previous: $exception);
        }

        if ($image === false) {
            throw new RuntimeException("Photo {$upload->getClientOriginalName()} is not a JPEG, PNG or WebP image.");
        }

        return $image;
    }

    private function upright(\GdImage $image, UploadedFile $upload): \GdImage
    {
        if ($upload->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        try {
            $orientation = exif_read_data($upload->getPathname())['Orientation'] ?? 1;
        } catch (ErrorException) {
            return $image;
        }

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function encode(\GdImage $image): string
    {
        // Transparent PNG pixels would encode as black; lay the image over white first.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image)) ?: throw new RuntimeException('The photo could not be re-encoded.');
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255) ?: 0);
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, 90);

        return (string) ob_get_clean();
    }
}
