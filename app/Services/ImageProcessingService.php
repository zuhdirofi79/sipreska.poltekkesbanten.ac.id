<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageProcessingService
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
    ) {}

    public function createThumbnail(string $sourcePath, string $destinationDirectory, int $activityId, int $maxWidth = 320): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $disk = Storage::disk($this->fileStorage->disk());
        $safeSource = $this->fileStorage->resolveSafePath($sourcePath);

        if (! $disk->exists($safeSource)) {
            return null;
        }

        $absoluteSource = $disk->path($safeSource);
        $imageInfo = @getimagesize($absoluteSource);

        if ($imageInfo === false) {
            return null;
        }

        [$width, $height, $type] = $imageInfo;

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $sourceImage = $this->createImageResource($absoluteSource, $type);

        if ($sourceImage === null) {
            return null;
        }

        $targetWidth = min($maxWidth, $width);
        $targetHeight = (int) round($height * ($targetWidth / $width));
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumbnail === false) {
            imagedestroy($sourceImage);

            return null;
        }

        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($thumbnail, null, 82);
        $binary = (string) ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        if ($binary === '') {
            return null;
        }

        $stored = $this->fileStorage->storeBinary(
            $binary,
            'documentation',
            $activityId,
            'jpg',
            'image/jpeg',
        );

        return $stored['path'];
    }

    private function createImageResource(string $absolutePath, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_GIF => @imagecreatefromgif($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };
    }
}
