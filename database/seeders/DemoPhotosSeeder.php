<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Media\Actions\AddImage;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Models\Property;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;

/**
 * Drawn photos for the demo properties, so the app and the panel show pictures: a sky, a building
 * and its windows, in colours that differ by property. Each goes through AddImage, the path a
 * resident's photo takes: cleaned, then its two sizes made on the `media` queue.
 *
 * Local and testing only. Properties that already have photos are left alone, so running it
 * again adds nothing.
 */
final class DemoPhotosSeeder extends Seeder
{
    public const int PHOTOS_PER_PROPERTY = 3;

    private const int WIDTH = 1600;

    private const int HEIGHT = 1200;

    private const int JPEG_QUALITY = 85;

    private const int FLOORS = 4;

    private const int WINDOWS_PER_FLOOR = 5;

    /** Façade colours, one picked per photo. */
    private const array FACADES = [[214, 196, 168], [196, 160, 120], [232, 220, 200], [180, 186, 190], [210, 180, 150]];

    public function run(AddImage $addImage): void
    {
        if (! App::environment(DemoContentSeeder::ENVIRONMENTS)) {
            throw new LogicException('Demo photos are made up: they are seeded in local and testing only.');
        }

        Property::query()->whereDoesntHave('media')->orderBy('id')->each(function (Property $property) use ($addImage): void {
            foreach (range(1, self::PHOTOS_PER_PROPERTY) as $shot) {
                $path = $this->draw($property, $shot);

                try {
                    $addImage->handle($property, Property::PHOTOS, $path);
                } finally {
                    File::delete($path);
                }
            }
        });
    }

    private function draw(Property $property, int $shot): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT) ?: throw new LogicException('GD could not make an image.');
        $seed = crc32($property->id.$shot);

        $this->sky($image, $seed);
        $this->building($image, $seed, $property->type);

        $path = sys_get_temp_dir().'/demo-photo-'.Str::uuid()->toString().'.jpg';
        imagejpeg($image, $path, self::JPEG_QUALITY);

        return $path;
    }

    private function sky(GdImage $image, int $seed): void
    {
        $evening = $seed % 3 === 0;

        foreach (range(0, self::HEIGHT - 1) as $y) {
            $share = $y / self::HEIGHT;
            $colour = $evening
                ? $this->colour($image, (int) (250 - 60 * $share), (int) (170 + 40 * $share), (int) (120 + 90 * $share))
                : $this->colour($image, (int) (120 + 80 * $share), (int) (170 + 50 * $share), (int) (235 - 10 * $share));
            imageline($image, 0, $y, self::WIDTH, $y, $colour);
        }

        // The ground: sand, as Sadat City's edges are.
        imagefilledrectangle($image, 0, (int) (self::HEIGHT * 0.82), self::WIDTH, self::HEIGHT, $this->colour($image, 205, 185, 150));
    }

    private function building(GdImage $image, int $seed, PropertyType $type): void
    {
        [$red, $green, $blue] = self::FACADES[$seed % count(self::FACADES)];
        $floors = in_array($type, [PropertyType::Villa, PropertyType::House, PropertyType::Shop], true) ? 2 : self::FLOORS;
        $left = 250 + $seed % 200;
        $right = self::WIDTH - 250 - $seed % 150;
        $ground = (int) (self::HEIGHT * 0.82);
        $floorHeight = 150;
        $top = $ground - $floors * $floorHeight;

        imagefilledrectangle($image, $left, $top, $right, $ground, $this->colour($image, $red, $green, $blue));
        imagefilledrectangle($image, $left - 20, $top - 25, $right + 20, $top, $this->colour($image, $red - 40, $green - 40, $blue - 40));

        $windowWidth = (int) (($right - $left) / (self::WINDOWS_PER_FLOOR * 2 + 1));

        foreach (range(0, $floors - 1) as $floor) {
            foreach (range(0, self::WINDOWS_PER_FLOOR - 1) as $window) {
                $x = $left + $windowWidth * (2 * $window + 1);
                $y = $top + $floor * $floorHeight + 40;
                imagefilledrectangle($image, $x, $y, $x + $windowWidth, $y + 70, $this->colour($image, 70, 95, 120));
            }
        }

        // The door, on the ground floor's middle.
        $door = (int) (($left + $right) / 2);
        imagefilledrectangle($image, $door - 45, $ground - 120, $door + 45, $ground, $this->colour($image, 90, 60, 40));
    }

    private function colour(GdImage $image, int $red, int $green, int $blue): int
    {
        $clamp = fn (int $value): int => max(0, min(255, $value));

        return imagecolorallocate($image, $clamp($red), $clamp($green), $clamp($blue)) ?: 0;
    }
}
