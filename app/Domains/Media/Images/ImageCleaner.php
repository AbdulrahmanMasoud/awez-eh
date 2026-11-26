<?php

declare(strict_types=1);

namespace App\Domains\Media\Images;

use App\Domains\Media\Exceptions\ImageTooLarge;
use App\Domains\Media\Exceptions\UnreadableImage;
use App\Domains\Media\Support\MediaTypes;
use finfo;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;

/**
 * The privacy step, before anything is stored: the image is decoded and written again as a JPEG
 * with no metadata (EXIF, GPS, the camera, the owner's name, comments), turned upright from its
 * EXIF orientation first and scaled down to the configured long side. Only the colour profile
 * stays, so a phone's wide-gamut photo keeps its colours. The uploaded bytes are never kept.
 */
final readonly class ImageCleaner
{
    public function __construct(private Config $config) {}

    public function clean(string $path): CleanedImage
    {
        $decoder = $this->decoderFor($path);

        try {
            [$width, $height] = $this->measure($decoder, $path);

            $image = $this->read($decoder, $path, $width, $height);
            $image = $this->normalize($image);
            $output = $this->write($image);

            return new CleanedImage($output, $image->getImageWidth(), $image->getImageHeight());
        } catch (ImagickException $exception) {
            throw new UnreadableImage($exception);
        }
    }

    /**
     * ImageMagick is told the format, so it never picks a decoder from the file itself (its SVG,
     * PostScript or MSL decoders are never reached).
     */
    private function decoderFor(string $path): string
    {
        $type = new finfo(FILEINFO_MIME_TYPE)->file($path);

        if (! is_string($type) || ! isset(MediaTypes::DECODERS[$type])) {
            throw new UnreadableImage;
        }

        return MediaTypes::DECODERS[$type];
    }

    /**
     * The stored size, read from the header without decoding; a canvas no phone makes is refused
     * here, before it can fill the memory.
     *
     * @return array{0: int, 1: int}
     */
    private function measure(string $decoder, string $path): array
    {
        $ping = new Imagick;
        $ping->pingImage("{$decoder}:{$path}[0]");
        $width = $ping->getImageWidth();
        $height = $ping->getImageHeight();
        $ping->clear();

        if ($width * $height > $this->config->integer('awez.media.max_pixels')) {
            throw new ImageTooLarge($width, $height);
        }

        return [$width, $height];
    }

    private function read(string $decoder, string $path, int $width, int $height): Imagick
    {
        $image = new Imagick;
        $scale = $this->config->integer('awez.media.max_long_side') / max($width, $height, 1);

        // libjpeg can decode at a fraction of the size (1/8 steps): a 48 MP photo never sits in
        // memory at full size. Only for a photo over the cap: below it, the hint would enlarge.
        if ($decoder === 'JPEG' && $scale < 1) {
            $image->setOption('jpeg:size', sprintf('%dx%d', (int) ceil($width * $scale), (int) ceil($height * $scale)));
        }

        // The first frame only: an animated WebP becomes its first picture.
        $image->readImage("{$decoder}:{$path}[0]");

        return $image;
    }

    private function normalize(Imagick $image): Imagick
    {
        $image->autoOrient();

        $profiles = $image->getImageProfiles('icc', true);
        $image->stripImage();

        if (isset($profiles['icc']) && is_string($profiles['icc'])) {
            $image->profileImage('icc', $profiles['icc']);
        }

        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        // JPEG has no transparency: a PNG's transparent parts become white, not black.
        if ($image->getImageAlphaChannel()) {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }

        $longSide = $this->config->integer('awez.media.max_long_side');

        if (max($image->getImageWidth(), $image->getImageHeight()) > $longSide) {
            $image->resizeImage($longSide, $longSide, Imagick::FILTER_LANCZOS, 1, true);
        }

        return $image;
    }

    private function write(Imagick $image): string
    {
        // A name no one can guess, in the system's temporary folder; Medialibrary moves it away.
        $path = sys_get_temp_dir().'/awez-image-'.Str::uuid()->toString().'.jpg';

        $image->setImageFormat('JPEG');
        $image->setImageCompressionQuality($this->config->integer('awez.media.jpeg_quality'));
        $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        $image->writeImage("JPEG:{$path}");

        return $path;
    }
}
