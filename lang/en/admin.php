<?php

declare(strict_types=1);

return [
    'brand' => 'Awez Eh',

    'navigation' => [
        'content' => 'Content',
        'activity' => 'Activity',
        'system' => 'System',
    ],

    'roles' => [
        'super_admin' => 'Super admin',
        'moderator' => 'Moderator',
        'data_entry' => 'Data entry',
        'sales' => 'Sales',
    ],

    'language' => [
        'switch' => 'العربية',
    ],

    'fields' => [
        'name_ar' => 'Arabic name',
        'name_en' => 'English name',
        'slug' => 'URL slug',
        'is_active' => 'Active',
        'sort_order' => 'Order',
        'icon' => 'Icon',
        'parent' => 'Parent category',
        'city' => 'City',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'center' => 'Centre',
        'districts_count' => 'Districts',
        'created_at' => 'Added',
        'updated_at' => 'Last changed',
    ],

    'cities' => [
        'label' => 'city',
        'plural' => 'Cities',
        'center_help' => 'The coordinates of the city centre. Picking it on a map comes in a later step.',
    ],

    'districts' => [
        'label' => 'district',
        'plural' => 'Districts',
        'center_help' => 'Optional.',
    ],

    'trades' => [
        'label' => 'trade',
        'plural' => 'Trades',
    ],

    'place_categories' => [
        'label' => 'category',
        'plural' => 'Place categories',
        'top_level' => 'Top level',
    ],

    'users' => [
        'label' => 'user',
        'plural' => 'Users',
        'fields' => [
            'avatar' => 'Picture',
            'name' => 'Name',
            'phone' => 'Mobile',
            'email' => 'Email',
            'status' => 'Status',
            'roles' => 'Roles',
            'joined' => 'Joined',
            'last_seen' => 'Last seen',
            'locale' => 'Language',
            'district' => 'District',
            'providers' => 'Signs in with',
            'devices' => 'Devices',
            'suspended_at' => 'Suspended at',
            'reason' => 'Reason',
        ],
        'statuses' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'deleted' => 'Deleted',
        ],
        'filters' => [
            'has_phone' => 'Confirmed a mobile',
            'is_provider' => 'Craftsman',
            'is_advertiser' => 'Advertiser',
            'is_admin' => 'Admin',
        ],
        'sections' => [
            'profile' => 'Profile',
            'sign_in' => 'Sign-in and devices',
            'content' => 'Content',
        ],
        'content' => [
            'properties' => 'Properties',
            'used_items' => 'Used items',
            'places' => 'Places',
            'requests' => 'Requests',
            'request_replies' => 'Replies',
            'bookings' => 'Bookings',
            'reviews' => 'Reviews',
        ],
        'no_devices' => 'No devices.',
        'no_providers' => 'None.',
        'actions' => [
            'suspend' => 'Suspend',
            'suspend_help' => 'The account is signed out everywhere and cannot use the app until it is reactivated.',
            'suspended' => 'The account is suspended.',
            'reactivate' => 'Reactivate',
            'reactivated' => 'The account is active again.',
            'roles' => 'Edit roles',
            'roles_updated' => 'Roles updated.',
            'reveal_phone' => 'Show number',
            'revealed_phone' => 'Number: :phone',
        ],
    ],

    'activity' => [
        'label' => 'Activity log',
        'fields' => [
            'admin' => 'Admin',
            'event' => 'Action',
            'subject' => 'Record',
            'changes' => 'Change',
            'reason' => 'Reason',
            'at' => 'Time',
            'from' => 'From',
            'until' => 'Until',
        ],
        'system' => 'System',
        'events' => [
            'settings_updated' => 'Settings changed',
            'viewed_private_media' => 'Private document opened',
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'suspended' => 'Suspended an account',
            'reactivated' => 'Reactivated an account',
            'roles_changed' => 'Changed roles',
            'phone_revealed' => 'Showed a number',
            'admin_created' => 'Created an admin',
            'admin_promoted' => 'Promoted to admin',
        ],
        'subjects' => [
            'media' => 'File',
            'user' => 'User',
            'city' => 'City',
            'district' => 'District',
            'trade' => 'Trade',
            'place_category' => 'Place category',
        ],
    ],

    'dashboard' => [
        'title' => 'Home',
        'morning' => 'Good morning, :name',
        'evening' => 'Good evening, :name',
        'waiting' => '{0} :date · nothing is waiting for you|{1} :date · one thing is waiting for your decision|[2,*] :date · :count things are waiting for your decision',
        'periods' => [
            'today' => 'Today',
            'week' => 'Week',
            'month' => 'Month',
        ],
        'pending' => [
            'label' => 'Waiting for review',
            'oldest' => 'Oldest: :age',
            'none' => 'Nothing waiting',
        ],
        'new_users' => [
            'label' => 'New users',
            'confirmed' => ':percent% confirmed a mobile',
        ],
        'published' => [
            'label' => 'Published listings',
            'split' => ':properties properties · :providers craftsmen · :places places · :used_items used items',
        ],
    ],
    'image_settings' => [
        'label' => 'Image settings',
        'sections' => [
            'uploads' => 'Uploads',
            'collections' => 'Most photos',
        ],
        'fields' => [
            'max_file_size_mb' => 'Largest photo',
            'uploads_per_hour' => 'Uploads per hour per account',
            'property_photos' => 'Property photos',
            'agreement_documents' => 'Brokerage agreement documents',
            'provider_work_photos' => 'Provider work photos',
            'place_photos' => 'Place photos',
            'used_item_photos' => 'Used item photos',
            'request_photos' => 'Request photos',
        ],
        'megabytes' => 'MB',
        'save' => 'Save',
        'saved' => 'Settings saved',
    ],
    'media' => [
        'open_document' => 'Open document',
    ],
    'settings_groups' => [
        'media' => 'Image settings',
    ],
];
