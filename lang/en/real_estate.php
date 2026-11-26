<?php

declare(strict_types=1);

return [
    'purposes' => ['sale' => 'For sale', 'rent' => 'For rent'],
    'types' => [
        'apartment' => 'Apartment', 'villa' => 'Villa', 'duplex' => 'Duplex', 'house' => 'House', 'land' => 'Land',
        'shop' => 'Shop', 'office' => 'Office', 'warehouse' => 'Warehouse', 'factory' => 'Factory',
    ],
    'finishings' => ['unfinished' => 'Unfinished', 'semi_finished' => 'Semi-finished', 'finished' => 'Finished', 'luxury' => 'Luxury'],
    'rent_periods' => ['monthly' => 'Monthly', 'yearly' => 'Yearly'],
    'platform_name' => 'Awez Eh',

    'notifications' => [
        'approve' => ['title' => 'Your listing is live', 'body' => 'Your property “:title” is now public.'],
        'reject' => ['title' => 'Your listing needs changes', 'body' => 'Your property “:title” was not published. See why, edit it and send it again.'],
        'take_down' => ['title' => 'Your listing was taken down', 'body' => 'Your property “:title” is no longer public. See why, edit it and send it again.'],
    ],

    'attributes' => [
        'purpose' => 'purpose', 'type' => 'type', 'title' => 'title', 'description' => 'description', 'price' => 'price',
        'rent_period' => 'rent period', 'area_m2' => 'area', 'bedrooms' => 'bedrooms', 'bathrooms' => 'bathrooms',
        'floor' => 'floor', 'furnished' => 'furnished', 'finishing' => 'finishing', 'district_id' => 'district',
        'address_note' => 'address note', 'lat' => 'latitude', 'lng' => 'longitude', 'radius_m' => 'radius',
        'photos' => 'photos', 'photos.*' => 'photo', 'submit' => 'submit', 'price_min' => 'minimum price',
        'price_max' => 'maximum price', 'area_min' => 'minimum area', 'area_max' => 'maximum area', 'bedrooms_min' => 'minimum bedrooms',
        'brokered' => 'brokered', 'sort' => 'sort', 'type.*' => 'type', 'district_id.*' => 'district',
    ],
];
