<?php

namespace App\Requests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class PhotoStore
{
    /**
     * Re-encodes the upload with GD, which drops EXIF and other metadata (including GPS) before
     * the file is kept or sent to a model.
     *
     * @return array{number: int, path: string, mime_type: string}
     */
    public function store(UploadedFile $upload, string $requestId, int $number): array
    {
        $image = match ($upload->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($upload->getPathname()),
            'image/png' => imagecreatefrompng($upload->getPathname()),
            'image/webp' => imagecreatefromwebp($upload->getPathname()),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('The photo could not be read as a JPEG, PNG or WebP image.');
        }

        $path = "requests/{$requestId}/photo-{$number}.jpg";
        $directory = Storage::disk('local')->path(dirname($path));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        imagejpeg($image, Storage::disk('local')->path($path), 90);
        imagedestroy($image);

        return ['number' => $number, 'path' => $path, 'mime_type' => 'image/jpeg'];
    }
}
