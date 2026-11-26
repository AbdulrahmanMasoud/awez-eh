<?php

declare(strict_types=1);

return [
    'validation' => [
        'required' => 'Send a photo in the file field.',
        'format' => 'The photo must be a JPEG, PNG or WebP.',
        'size' => 'The photo is larger than :max MB.',
    ],
];
