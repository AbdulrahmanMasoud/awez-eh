<?php

declare(strict_types=1);

return [
    'validation' => [
        'required' => 'ابعت صورة في حقل file.',
        'format' => 'الصورة لازم تكون JPEG أو PNG أو WebP.',
        'size' => 'الصورة أكبر من :max ميجابايت.',
    ],
];
