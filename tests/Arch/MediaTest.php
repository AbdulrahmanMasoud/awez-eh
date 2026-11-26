<?php

declare(strict_types=1);

use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Lines under app/ that name a disk with a string: media takes its disks from config('awez.media.*').
 *
 * @return list<string>
 */
function diskNamesInCode(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));
    $found = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (preg_match("/(useDisk|disk|toMediaCollection\\([^,)]+,|storingConversionsOnDisk|conversionsDisk)\\(\\s*['\"]/", $line) === 1
                || preg_match("/['\"](public|local|r2|r2_private|s3)['\"]\\s*\\)?\\s*;?\\s*\\/\\/\\s*disk/i", $line) === 1) {
                $found[] = basename($file->getPathname()).':'.($number + 1).': '.trim($line);
            }
        }
    }

    return $found;
}

it('names no disk for media outside config/', function (): void {
    expect(diskNamesInCode())->toBe([]);
});

it('gives every model with media the shared contract and trait: disks from config, limits from the settings', function (): void {
    $withMedia = array_filter(modelClasses(), fn (string $model): bool => in_array(InteractsWithMedia::class, class_uses_recursive($model), true));
    $withoutContract = array_filter($withMedia, fn (string $model): bool => ! is_a($model, HasMediaCollections::class, true)
        || ! in_array(InteractsWithMediaCollections::class, class_uses_recursive($model), true));

    expect(array_values($withMedia))->toHaveCount(11)
        ->and(array_values($withoutContract))->toBe([]);
});

arch('the media contract is an interface')
    ->expect(HasMediaCollections::class)
    ->toBeInterface();

arch('only the image cleaner touches Imagick')
    ->expect('Imagick')
    ->toOnlyBeUsedIn(['App\Domains\Media\Images']);
