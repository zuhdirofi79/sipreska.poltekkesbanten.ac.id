<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;
use Illuminate\Support\Facades\Log;

final class AttendanceQrImage
{
    private const PREVIEW_SCALE = 10;

    private const DOWNLOAD_SIZE = 1024;

    private const MIN_SIZE = 256;

    private const MAX_SIZE = 2048;

    /** White circle diameter as fraction of QR width (24–30%). */
    private const CIRCLE_DIAMETER_RATIO = 0.27;

    /** Logo max edge as fraction of circle diameter (58–68%). */
    private const LOGO_TO_CIRCLE_RATIO = 0.63;

    /** Near-white / dirty-gray canvas threshold (inclusive). */
    private const BG_CHANNEL_THRESHOLD = 225;

    public static function generate(string $url, bool $forDownload = false, ?int $size = null): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::H,
            'scale' => self::PREVIEW_SCALE,
            'imageTransparent' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        $png = (new QRCode($options))->render($url);

        if ($forDownload) {
            $png = self::fitToSquare($png, self::normalizeSize($size ?? self::DOWNLOAD_SIZE));
        }

        return self::overlayCenteredLogoWithWhiteCircle($png);
    }

    private static function normalizeSize(int $size): int
    {
        return max(self::MIN_SIZE, min(self::MAX_SIZE, $size));
    }

    private static function fitToSquare(string $pngData, int $size): string
    {
        if (! self::gdAvailable()) {
            return $pngData;
        }

        $source = @imagecreatefromstring($pngData);

        if ($source === false) {
            return $pngData;
        }

        $destination = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $margin = (int) round($size * 0.06);
        $drawSize = $size - ($margin * 2);

        imagecopyresampled(
            $destination,
            $source,
            $margin,
            $margin,
            0,
            0,
            $drawSize,
            $drawSize,
            $sourceWidth,
            $sourceHeight,
        );

        $result = self::outputPng($destination, $pngData);

        imagedestroy($source);
        imagedestroy($destination);

        return $result;
    }

    private static function overlayCenteredLogoWithWhiteCircle(string $pngData): string
    {
        if (! self::gdAvailable()) {
            Log::warning('GD extension not available, QR generated without logo overlay.');

            return $pngData;
        }

        $logoPath = self::resolveLogoPath();

        if ($logoPath === null) {
            return $pngData;
        }

        $qr = @imagecreatefromstring($pngData);

        if ($qr === false) {
            return $pngData;
        }

        $logo = self::loadLogoImage($logoPath);

        if ($logo === false) {
            imagedestroy($qr);
            Log::warning('Attendance QR logo could not be loaded.', ['path' => $logoPath]);

            return $pngData;
        }

        $qrWidth = imagesx($qr);
        $qrHeight = imagesy($qr);
        $base = min($qrWidth, $qrHeight);

        $circleDiameter = max(2, (int) round($base * self::CIRCLE_DIAMETER_RATIO));
        if ($circleDiameter % 2 === 0) {
            $circleDiameter++;
        }

        $logoMaxSize = max(1, (int) round($circleDiameter * self::LOGO_TO_CIRCLE_RATIO));
        $resizedLogo = self::resizeLogoPreservingAlpha($logo, $logoMaxSize);

        if ($resizedLogo === false) {
            imagedestroy($logo);
            imagedestroy($qr);
            Log::warning('Attendance QR logo could not be loaded.', ['path' => $logoPath]);

            return $pngData;
        }

        $centerX = (int) round(($qrWidth - 1) / 2);
        $centerY = (int) round(($qrHeight - 1) / 2);
        $white = imagecolorallocate($qr, 255, 255, 255);

        // Circle badge is drawn by code — never use the logo file canvas as the badge.
        imagefilledellipse($qr, $centerX, $centerY, $circleDiameter, $circleDiameter, $white);

        $logoWidth = imagesx($resizedLogo);
        $logoHeight = imagesy($resizedLogo);
        $logoX = (int) round(($qrWidth - $logoWidth) / 2);
        $logoY = (int) round(($qrHeight - $logoHeight) / 2);

        imagealphablending($qr, true);
        imagecopy($qr, $resizedLogo, $logoX, $logoY, 0, 0, $logoWidth, $logoHeight);

        $result = self::outputPng($qr, $pngData);

        imagedestroy($resizedLogo);
        imagedestroy($logo);
        imagedestroy($qr);

        return $result;
    }

    private static function resolveLogoPath(): ?string
    {
        $candidates = [
            public_path('images/logo-poltekkes-banten.png'),
            public_path('images/Logo Poltekkes Banten (2).png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        Log::warning('Attendance QR logo file not found.', ['paths' => $candidates]);

        return null;
    }

    private static function loadLogoImage(string $path): GdImage|false
    {
        $image = @imagecreatefrompng($path);

        if ($image === false) {
            $bytes = @file_get_contents($path);
            $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        }

        if ($image === false) {
            return false;
        }

        $cleaned = self::cleanLogoBackground($image);
        imagedestroy($image);

        if ($cleaned === false) {
            return false;
        }

        $cropped = self::cropTransparentOrWhitePadding($cleaned);

        if ($cropped !== $cleaned) {
            imagedestroy($cleaned);
        }

        return $cropped;
    }

    /**
     * Remove dirty canvas (near-white / light-gray) so only logo artwork remains.
     * Source file corners are typically ~#F6F6F6, not transparent.
     */
    private static function cleanLogoBackground(GdImage $logo): GdImage|false
    {
        $width = imagesx($logo);
        $height = imagesy($logo);
        $clean = imagecreatetruecolor($width, $height);

        if ($clean === false) {
            return false;
        }

        imagealphablending($clean, false);
        imagesavealpha($clean, true);

        $transparent = imagecolorallocatealpha($clean, 0, 0, 0, 127);
        imagefilledrectangle($clean, 0, 0, $width - 1, $height - 1, $transparent);

        $isTrueColor = imageistruecolor($logo);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($logo, $x, $y);

                if ($isTrueColor) {
                    $a = ($rgba & 0x7F000000) >> 24;
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                } else {
                    $channels = imagecolorsforindex($logo, $rgba);
                    $r = $channels['red'];
                    $g = $channels['green'];
                    $b = $channels['blue'];
                    $a = $channels['alpha'];
                }

                if ($a >= 127 || self::isDirtyBackgroundPixel($r, $g, $b)) {
                    imagesetpixel($clean, $x, $y, $transparent);

                    continue;
                }

                imagesetpixel(
                    $clean,
                    $x,
                    $y,
                    imagecolorallocatealpha($clean, $r, $g, $b, $a),
                );
            }
        }

        return $clean;
    }

    private static function isDirtyBackgroundPixel(int $r, int $g, int $b): bool
    {
        return $r >= self::BG_CHANNEL_THRESHOLD
            && $g >= self::BG_CHANNEL_THRESHOLD
            && $b >= self::BG_CHANNEL_THRESHOLD;
    }

    private static function cropTransparentOrWhitePadding(GdImage $logo): GdImage
    {
        $width = imagesx($logo);
        $height = imagesy($logo);

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($logo, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;

                if ($a >= 127) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if (self::isDirtyBackgroundPixel($r, $g, $b)) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return $logo;
        }

        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;

        if ($cropW === $width && $cropH === $height && $minX === 0 && $minY === 0) {
            return $logo;
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);

        if ($cropped === false) {
            return $logo;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefilledrectangle($cropped, 0, 0, $cropW - 1, $cropH - 1, $transparent);
        imagecopy($cropped, $logo, 0, 0, $minX, $minY, $cropW, $cropH);

        return $cropped;
    }

    private static function resizeLogoPreservingAlpha(GdImage $logo, int $maxSize): GdImage|false
    {
        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        $scale = min($maxSize / max(1, $srcW), $maxSize / max(1, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $resized = imagecreatetruecolor($dstW, $dstH);

        if ($resized === false) {
            return false;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $dstW - 1, $dstH - 1, $transparent);

        imagecopyresampled(
            $resized,
            $logo,
            0,
            0,
            0,
            0,
            $dstW,
            $dstH,
            $srcW,
            $srcH,
        );

        return $resized;
    }

    private static function outputPng(GdImage $image, string $fallback): string
    {
        ob_start();
        imagepng($image);
        $result = ob_get_clean();

        return $result !== false && $result !== '' ? $result : $fallback;
    }

    private static function gdAvailable(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagepng');
    }
}
