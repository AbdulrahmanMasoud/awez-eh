<?php

declare(strict_types=1);

use App\Domains\Media\Models\Media;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Webp;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\Downloaders\DefaultDownloader;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;
use Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/*
| spatie/laravel-medialibrary, kept to what Awez Eh uses. Every collection names its disk from
| config('awez.media.*') (App\Domains\Media\Support\MediaDisks); `disk_name` is only the package's
| fallback, read from the same variable.
*/
return [

    'disk_name' => env('MEDIA_DISK', 'public'),

    // Conversions live beside their original.
    'conversions_disk_name' => null,

    // The cleaned original is far smaller; the upload's own limit is MediaSettings'.
    'max_file_size' => 1024 * 1024 * 10,

    'disallowed_extensions' => FileAdder::$defaultDisallowedExtensions,

    'allowed_extensions' => null,

    'queue_connection_name' => env('QUEUE_CONNECTION', 'database'),

    'queue_name' => 'media',

    'queue_conversions_by_default' => true,

    // A conversion job never runs before the row it reads is committed.
    'queue_conversions_after_database_commit' => true,

    'media_model' => Media::class,

    'media_observer' => MediaObserver::class,

    'use_default_collection_serialization' => false,

    'temporary_upload_model' => null,

    'enable_temporary_uploads_session_affinity' => false,

    'generate_thumbnails_for_temporary_uploads' => false,

    'file_namer' => DefaultFileNamer::class,

    // `{media id}/file`: the folder belongs to the media row, so a file never moves when its row
    // changes owner (AttachUploads).
    'path_generator' => DefaultPathGenerator::class,

    'file_remover_class' => DefaultFileRemover::class,

    'custom_path_generators' => [],

    'url_generator' => DefaultUrlGenerator::class,

    'moves_media_on_update' => false,

    'version_urls' => false,

    // Conversions are not optimized by external binaries (none is installed): WebP's own quality
    // setting does the work.
    'image_optimizers' => [],

    'image_generators' => [
        Image::class,
        Webp::class,
    ],

    'temporary_directory_path' => null,

    // Imagick, as the cleaning step: it keeps a photo's colour profile in the conversions.
    'image_driver' => 'imagick',

    'ffmpeg_path' => '/usr/bin/ffmpeg',

    'ffprobe_path' => '/usr/bin/ffprobe',

    'ffmpeg_timeout' => 900,

    'ffmpeg_threads' => 0,

    'jobs' => [
        'perform_conversions' => PerformConversionsJob::class,
        'generate_responsive_images' => GenerateResponsiveImagesJob::class,
    ],

    'media_downloader' => DefaultDownloader::class,

    'media_downloader_ssl' => true,

    // Minutes; the panel asks for its own (awez.media.signed_url_minutes).
    'temporary_url_default_lifetime' => 5,

    'remote' => [
        'extra_headers' => [
            // Conversions never change under their URL: a new photo is a new media row.
            'CacheControl' => 'max-age=31536000, immutable',
        ],
    ],

    'responsive_images' => [
        'width_calculator' => FileSizeOptimizedWidthCalculator::class,
        'use_tiny_placeholders' => false,
        'tiny_placeholder_generator' => Blurred::class,
    ],

    'enable_vapor_uploads' => false,

    'default_loading_attribute_value' => null,

    'prefix' => '',

    'force_lazy_loading' => true,

];
