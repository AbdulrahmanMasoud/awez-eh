<?php

declare(strict_types=1);

return [
    // The common reasons a moderator gives, in the reject form beside free text.
    'rejection_reasons' => [
        'unclear_photos' => 'The photos are not clear.',
        'missing_details' => 'Details are missing.',
        'wrong_price' => 'The price does not make sense.',
        'duplicate' => 'The listing is a duplicate.',
        'wrong_category' => 'The type or category is wrong.',
        'contact_in_text' => 'The description carries a phone number or other contact.',
        'other' => 'Another reason',
    ],
];
