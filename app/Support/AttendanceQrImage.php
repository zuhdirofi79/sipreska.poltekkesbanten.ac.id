<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class AttendanceQrImage
{
    private const PREVIEW_SCALE = 10;

    private const DOWNLOAD_SIZE = 1024;

    public static function generate(string $url, bool $forDownload = false): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'scale' => self::PREVIEW_SCALE,
            'imageTransparent' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        $png = (new QRCode($options))->render($url);

        if ($forDownload) {
            return self::fitToSquare($png, self::DOWNLOAD_SIZE);
        }

        return $png;
    }

    private static function fitToSquare(string $pngData, int $size): string
    {
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

        ob_start();
        imagepng($destination);
        $result = ob_get_clean() ?: $pngData;

        imagedestroy($source);
        imagedestroy($destination);

        return $result;
    }
}
