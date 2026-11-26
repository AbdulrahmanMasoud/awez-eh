<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Media\Settings\MediaSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Sanctum::actingAs($this->user = User::factory()->create());
});

/**
 * The stored media of the upload the response names.
 */
function uploadedMedia(string $uploadId): Media
{
    $media = PendingUpload::query()->findOrFail($uploadId)->file();
    expect($media)->toBeInstanceOf(Media::class);

    return $media;
}

function storedBytes(Media $media, string $conversion = ''): string
{
    $disk = $conversion === '' ? $media->disk : (string) $media->conversions_disk;

    return (string) Storage::disk($disk)->get($media->getPathRelativeToRoot($conversion));
}

/**
 * @return array<string, mixed>
 */
function exifOf(string $bytes): array
{
    $path = Images::file($bytes);

    return @exif_read_data($path, null, true) ?: [];
}

it('stores every accepted format as a clean JPEG with two WebP sizes', function (string $bytes, string $name): void {
    $response = $this->post('/api/v1/uploads', ['file' => Images::upload($bytes, $name)])
        ->assertCreated()
        ->assertJsonPath('data.processing', false);

    $media = uploadedMedia($response->json('data.id'));

    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->file_name)->toBe('photo.jpg')
        ->and(new finfo(FILEINFO_MIME_TYPE)->buffer(storedBytes($media)))->toBe('image/jpeg')
        ->and(new finfo(FILEINFO_MIME_TYPE)->buffer(storedBytes($media, 'thumb')))->toBe('image/webp')
        ->and(new finfo(FILEINFO_MIME_TYPE)->buffer(storedBytes($media, 'large')))->toBe('image/webp')
        ->and($response->json('data.thumb_url'))->toEndWith("/{$media->id}/conversions/photo-thumb.webp")
        ->and($response->json('data.large_url'))->toEndWith("/{$media->id}/conversions/photo-large.webp");
})->with([
    'JPEG' => [fn (): string => Images::jpeg(), 'IMG_2041.JPG'],
    'PNG' => [fn (): string => Images::png(), 'screenshot.png'],
    'WebP' => [fn (): string => Images::webp(), 'photo.webp'],
    // Named as a PNG: the content decides, not the name.
    'a JPEG named .png' => [fn (): string => Images::jpeg(), 'photo.png'],
]);

it('removes the location, the camera and the owner\'s name, and turns the photo upright', function (): void {
    $original = Images::jpegWithExif(400, 200, orientation: 6);
    expect(exifOf($original)['GPS'] ?? null)->not->toBeNull()
        ->and(exifOf($original)['IFD0']['Make'] ?? null)->toBe(Images::MAKE);

    $response = $this->post('/api/v1/uploads', ['file' => Images::upload($original)])->assertCreated();
    $media = uploadedMedia($response->json('data.id'));
    $stored = storedBytes($media);

    // Read back from the stored original and both sizes: no EXIF section is left at all.
    foreach ([$stored, storedBytes($media, 'thumb'), storedBytes($media, 'large')] as $bytes) {
        $exif = exifOf($bytes);

        expect($exif)->not->toHaveKeys(['IFD0', 'GPS', 'EXIF'])
            ->and($bytes)->not->toContain(Images::MAKE)
            ->and($bytes)->not->toContain(Images::MODEL)
            ->and($bytes)->not->toContain(Images::ARTIST);
    }

    // Stored wide with "turn clockwise", it is now tall: red (the left half) on top, blue below.
    [$red, , $blue] = Images::rgbAt($stored, 100, 50);
    [$red2, , $blue2] = Images::rgbAt($stored, 100, 350);

    expect([$media->width, $media->height])->toBe([200, 400])
        ->and($response->json('data.width'))->toBe(200)
        ->and($response->json('data.height'))->toBe(400)
        ->and($red)->toBeGreaterThan($blue)
        ->and($blue2)->toBeGreaterThan($red2);
});

it('never keeps the uploaded bytes or the client\'s file name', function (): void {
    $original = Images::jpegWithExif();

    $response = $this->post('/api/v1/uploads', ['file' => Images::upload($original, 'ahmed-house-gps.jpg')])->assertCreated();
    $media = uploadedMedia($response->json('data.id'));

    $files = [...Storage::disk('local')->allFiles(), ...Storage::disk('public')->allFiles()];

    expect(storedBytes($media))->not->toBe($original)
        ->and($media->name)->toBe('photo')
        ->and(implode(' ', $files))->not->toContain('ahmed')
        ->and($files)->toHaveCount(3);
});

it('keeps the original on the private disk: only the two sizes are public', function (): void {
    $response = $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated();
    $media = uploadedMedia($response->json('data.id'));
    $original = $media->getPathRelativeToRoot();

    expect($media->disk)->toBe('local')
        ->and($media->conversions_disk)->toBe('public')
        ->and(Storage::disk('local')->allFiles())->toBe([$original])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing([
            $media->getPathRelativeToRoot('thumb'),
            $media->getPathRelativeToRoot('large'),
        ]);

    // The URL a size's URL suggests for the original leads nowhere.
    $guessed = str_replace('conversions/photo-thumb.webp', 'photo.jpg', (string) $response->json('data.thumb_url'));
    expect($guessed)->toEndWith("/storage/{$original}");
    $this->get("/storage/{$original}")->assertForbidden();
});

it('makes each size at its width, never enlarged, and caps the original', function (int $width, int $height, array $original, array $thumb, array $large): void {
    $response = $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg($width, $height))])->assertCreated();
    $media = uploadedMedia($response->json('data.id'));

    $size = fn (string $conversion): array => array_slice(getimagesizefromstring(storedBytes($media, $conversion)) ?: [], 0, 2);

    expect($size(''))->toBe($original)
        ->and([$media->width, $media->height])->toBe($original)
        ->and($size('thumb'))->toBe($thumb)
        ->and($size('large'))->toBe($large);
})->with([
    'wide, over the long side' => [4000, 2000, [2560, 1280], [480, 240], [1600, 800]],
    'tall' => [1200, 2400, [1200, 2400], [480, 960], [1200, 2400]],
    'small' => [300, 200, [300, 200], [300, 200], [300, 200]],
]);

it('turns transparent parts white, not black', function (): void {
    $response = $this->post('/api/v1/uploads', ['file' => Images::upload(Images::png(300, 300, transparent: true), 'logo.png')])->assertCreated();

    expect(Images::rgbAt(storedBytes(uploadedMedia($response->json('data.id'))), 150, 40))->each->toBeGreaterThan(240);
});

it('refuses what is not an accepted image, with an Arabic message', function (Closure $file, string $message): void {
    $this->post('/api/v1/uploads', ['file' => $file()], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.file.0', $message);

    expect(PendingUpload::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'a text file named .jpg' => [fn (): UploadedFile => Images::upload('just some text, not a photo', 'photo.jpg'), 'الصورة لازم تكون JPEG أو PNG أو WebP.'],
    'an executable named .jpg' => [fn (): UploadedFile => Images::upload("\x7FELF\x02\x01\x01\0".str_repeat("\0", 120), 'photo.jpg'), 'الصورة لازم تكون JPEG أو PNG أو WebP.'],
    'an SVG' => [fn (): UploadedFile => Images::upload('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>', 'photo.svg'), 'الصورة لازم تكون JPEG أو PNG أو WebP.'],
    // The container's ImageMagick has no HEVC decoder: the app sends JPEG instead.
    'a HEIC' => [fn (): UploadedFile => Images::upload((string) file_get_contents(base_path('tests/Fixtures/Media/photo.heic')), 'IMG_0001.HEIC'), 'الصورة لازم تكون JPEG أو PNG أو WebP.'],
    'no file' => [fn (): string => 'photo', 'ابعت صورة في حقل file.'],
]);

it('refuses a file over the size in the settings', function (): void {
    MediaSettings::fake(['max_file_size_mb' => 1]);

    // A real JPEG, padded past 1 MB with a comment segment.
    $jpeg = Images::jpeg();
    $padded = substr($jpeg, 0, 2)."\xFF\xFE".pack('n', 65000).str_repeat('x', 64998);
    $padded .= str_repeat("\xFF\xFE".pack('n', 65000).str_repeat('x', 64998), 16).substr($jpeg, 2);

    $this->post('/api/v1/uploads', ['file' => Images::upload($padded)], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.file.0', 'الصورة أكبر من 1 ميجابايت.');
});

it('refuses a file that looks like a JPEG but cannot be read', function (): void {
    $broken = substr(Images::jpeg(), 0, 200);

    $this->post('/api/v1/uploads', ['file' => Images::upload($broken)], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'unreadable_image');

    expect(PendingUpload::query()->count())->toBe(0);
});

it('refuses a canvas larger than any phone photo before decoding it', function (): void {
    config(['awez.media.max_pixels' => 10_000]);

    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg(200, 100))], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'image_too_large');
});

it('answers in English when asked', function (): void {
    $this->post('/api/v1/uploads', ['file' => Images::upload('text', 'photo.jpg')], ['Accept' => 'application/json', 'Accept-Language' => 'en'])
        ->assertJsonPath('errors.file.0', 'The photo must be a JPEG, PNG or WebP.');
});
