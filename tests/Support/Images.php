<?php

declare(strict_types=1);

namespace Tests\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Real image files for tests, made with GD. `jpegWithExif()` writes the EXIF a phone writes (GPS,
 * the camera, the owner's name, the orientation) into a JPEG by hand, so a test can prove it is gone.
 */
final class Images
{
    public const string MAKE = 'Awez Test Phones';

    public const string MODEL = 'Pixel Sadat 9';

    public const string ARTIST = 'Ahmed El Sayed';

    /** Sadat City, 30.3609 N 30.5226 E. */
    public const array GPS = ['lat' => [30, 21, 39], 'lon' => [30, 31, 21]];

    /**
     * A JPEG whose left half is red and right half blue, carrying phone EXIF. Orientation 6 means
     * "turn 90° clockwise to show": stored wide, it is a tall photo.
     */
    public static function jpegWithExif(int $width = 400, int $height = 200, int $orientation = 1): string
    {
        $jpeg = self::encode(self::halves($width, $height), 'jpeg');

        // An APP1 segment right after the start-of-image marker.
        $exif = "Exif\0\0".self::tiff($orientation);
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    public static function jpeg(int $width = 400, int $height = 200): string
    {
        return self::encode(self::halves($width, $height), 'jpeg');
    }

    public static function png(int $width = 300, int $height = 300, bool $transparent = false): string
    {
        $image = self::halves($width, $height);

        if ($transparent) {
            imagesavealpha($image, true);
            imagealphablending($image, false);
            imagefilledrectangle($image, 0, 0, $width - 1, intdiv($height, 2) - 1, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        }

        return self::encode($image, 'png');
    }

    public static function webp(int $width = 300, int $height = 200): string
    {
        return self::encode(self::halves($width, $height), 'webp');
    }

    /**
     * An upload of these bytes under this name, as PHP hands one to the app.
     */
    public static function upload(string $bytes, string $name = 'photo.jpg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'awez-test-');

        if ($path === false || file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('No temporary file for a test upload.');
        }

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * A file on disk with these bytes, for Actions that take a path.
     */
    public static function file(string $bytes): string
    {
        return self::upload($bytes)->getRealPath();
    }

    /**
     * @return array{0: int, 1: int, 2: int} the RGB of one pixel
     */
    public static function rgbAt(string $bytes, int $x, int $y): array
    {
        $image = imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Not an image.');
        }

        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    private static function halves(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not make an image.');
        }

        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 220, 20, 20));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 20, 20, 220));

        return $image;
    }

    private static function encode(GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 95),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
        };

        return (string) ob_get_clean();
    }

    /**
     * A little-endian TIFF block: IFD0 (Make, Model, Orientation, Artist, a pointer to the GPS IFD)
     * and the GPS IFD (latitude and longitude, with their N and E).
     */
    private static function tiff(int $orientation): string
    {
        $ifd0Offset = 8;
        $ifd0Count = 5;
        $gpsOffset = $ifd0Offset + 2 + $ifd0Count * 12 + 4;
        $gpsCount = 4;
        $data = $gpsOffset + 2 + $gpsCount * 12 + 4;

        $blobs = '';
        $place = function (string $bytes) use (&$blobs, $data): int {
            $offset = $data + strlen($blobs);
            $blobs .= $bytes.(strlen($bytes) % 2 === 1 ? "\0" : '');

            return $offset;
        };
        $entry = fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");
        $ascii = function (int $tag, string $text) use ($entry, $place): string {
            $bytes = $text."\0";

            return strlen($bytes) <= 4
                ? $entry($tag, 2, strlen($bytes), $bytes)
                : $entry($tag, 2, strlen($bytes), pack('V', $place($bytes)));
        };
        $rationals = fn (array $parts): string => implode('', array_map(fn (int $part): string => pack('VV', $part, 1), $parts));

        $ifd0 = pack('v', $ifd0Count)
            .$ascii(0x010F, self::MAKE)
            .$ascii(0x0110, self::MODEL)
            .$entry(0x0112, 3, 1, pack('v', $orientation))
            .$ascii(0x013B, self::ARTIST)
            .$entry(0x8825, 4, 1, pack('V', $gpsOffset))
            .pack('V', 0);

        $gps = pack('v', $gpsCount)
            .$ascii(0x0001, 'N')
            .$entry(0x0002, 5, 3, pack('V', $place($rationals(self::GPS['lat']))))
            .$ascii(0x0003, 'E')
            .$entry(0x0004, 5, 3, pack('V', $place($rationals(self::GPS['lon']))))
            .pack('V', 0);

        return "II\x2A\0".pack('V', $ifd0Offset).$ifd0.$gps.$blobs;
    }
}
