<?php

declare(strict_types=1);

return [
    'brand' => 'عاوز إيه',

    'navigation' => [
        'content' => 'المحتوى',
        'activity' => 'النشاط',
        'system' => 'النظام',
    ],

    'roles' => [
        'super_admin' => 'مدير النظام',
        'moderator' => 'مراجع المحتوى',
        'data_entry' => 'إدخال بيانات',
        'sales' => 'مبيعات',
    ],

    'language' => [
        'switch' => 'English',
    ],

    'fields' => [
        'name_ar' => 'الاسم بالعربي',
        'name_en' => 'الاسم بالإنجليزي',
        'slug' => 'المعرّف في الرابط',
        'is_active' => 'مفعّل',
        'sort_order' => 'الترتيب',
        'icon' => 'الأيقونة',
        'parent' => 'القسم الأب',
        'city' => 'المدينة',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'center' => 'المركز',
        'districts_count' => 'عدد المناطق',
        'created_at' => 'اتضاف',
        'updated_at' => 'آخر تعديل',
    ],

    'cities' => [
        'label' => 'مدينة',
        'plural' => 'المدن',
        'center_help' => 'إحداثيات وسط المدينة. اختيار المكان من الخريطة جاي في خطوة لاحقة.',
    ],

    'districts' => [
        'label' => 'منطقة',
        'plural' => 'المناطق',
        'center_help' => 'اختياري.',
    ],

    'trades' => [
        'label' => 'حرفة',
        'plural' => 'الحرف',
    ],

    'place_categories' => [
        'label' => 'قسم',
        'plural' => 'أقسام الأماكن',
        'top_level' => 'قسم رئيسي',
    ],

    'users' => [
        'label' => 'مستخدم',
        'plural' => 'المستخدمين',
        'fields' => [
            'avatar' => 'الصورة',
            'name' => 'الاسم',
            'phone' => 'الموبايل',
            'email' => 'الإيميل',
            'status' => 'الحالة',
            'roles' => 'الصلاحيات',
            'joined' => 'انضم',
            'last_seen' => 'آخر ظهور',
            'locale' => 'اللغة',
            'district' => 'المنطقة',
            'providers' => 'تسجيل الدخول بـ',
            'devices' => 'الأجهزة',
            'suspended_at' => 'اتوقف في',
            'reason' => 'السبب',
        ],
        'statuses' => [
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'deleted' => 'محذوف',
        ],
        'filters' => [
            'has_phone' => 'مأكد رقم الموبايل',
            'is_provider' => 'صنايعي',
            'is_advertiser' => 'معلن',
            'is_admin' => 'أدمن',
        ],
        'sections' => [
            'profile' => 'البيانات',
            'sign_in' => 'تسجيل الدخول والأجهزة',
            'content' => 'المحتوى',
        ],
        'content' => [
            'properties' => 'عقارات',
            'used_items' => 'مستعمل',
            'places' => 'أماكن',
            'requests' => 'طلبات',
            'request_replies' => 'ردود',
            'bookings' => 'حجوزات',
            'reviews' => 'تقييمات',
        ],
        'no_devices' => 'مفيش أجهزة مسجّلة.',
        'no_providers' => 'لا شيء.',
        'actions' => [
            'suspend' => 'إيقاف الحساب',
            'suspend_help' => 'الحساب هيخرج من كل الأجهزة ومش هيقدر يستخدم التطبيق لحد ما يتفعّل تاني.',
            'suspended' => 'الحساب اتوقف.',
            'reactivate' => 'تفعيل الحساب',
            'reactivated' => 'الحساب اتفعّل.',
            'roles' => 'تعديل الصلاحيات',
            'roles_updated' => 'الصلاحيات اتعدّلت.',
            'reveal_phone' => 'إظهار الرقم',
            'revealed_phone' => 'الرقم: :phone',
        ],
    ],

    'activity' => [
        'label' => 'سجل النشاط',
        'fields' => [
            'admin' => 'الأدمن',
            'event' => 'الإجراء',
            'subject' => 'السجل',
            'changes' => 'التغيير',
            'reason' => 'السبب',
            'at' => 'الوقت',
            'from' => 'من',
            'until' => 'لحد',
        ],
        'system' => 'النظام',
        'events' => [
            'settings_updated' => 'تعديل إعدادات',
            'viewed_private_media' => 'فتح مستند خاص',
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'suspended' => 'إيقاف حساب',
            'reactivated' => 'تفعيل حساب',
            'roles_changed' => 'تعديل صلاحيات',
            'phone_revealed' => 'إظهار رقم',
            'admin_created' => 'إنشاء أدمن',
            'admin_promoted' => 'ترقية لأدمن',
        ],
        'subjects' => [
            'media' => 'ملف',
            'user' => 'مستخدم',
            'city' => 'مدينة',
            'district' => 'منطقة',
            'trade' => 'حرفة',
            'place_category' => 'قسم أماكن',
        ],
    ],

    'dashboard' => [
        'title' => 'الرئيسية',
        'morning' => 'صباح الخير يا :name',
        'evening' => 'مساء الخير يا :name',
        'waiting' => '{0} :date · مفيش حاجة مستنية قرارك|{1} :date · عندك حاجة واحدة مستنية قرارك|[2,*] :date · عندك :count حاجة مستنية قرارك',
        'periods' => [
            'today' => 'النهارده',
            'week' => 'الأسبوع',
            'month' => 'الشهر',
        ],
        'pending' => [
            'label' => 'مستني مراجعة',
            'oldest' => 'أقدم واحد من :age',
            'none' => 'مفيش حاجة مستنية',
        ],
        'new_users' => [
            'label' => 'مستخدمين جداد',
            'confirmed' => ':percent% أكدوا الموبايل',
        ],
        'published' => [
            'label' => 'إعلانات منشورة',
            'split' => ':properties عقار · :providers صنايعي · :places مكان · :used_items مستعمل',
        ],
    ],
    'image_settings' => [
        'label' => 'إعدادات الصور',
        'sections' => [
            'uploads' => 'الرفع',
            'collections' => 'أقصى عدد صور',
        ],
        'fields' => [
            'max_file_size_mb' => 'أقصى حجم للصورة',
            'uploads_per_hour' => 'عدد الرفعات في الساعة لكل حساب',
            'property_photos' => 'صور العقار',
            'agreement_documents' => 'مستندات عقد الوساطة',
            'provider_work_photos' => 'صور شغل الصنايعي',
            'place_photos' => 'صور المكان',
            'used_item_photos' => 'صور المستعمل',
            'request_photos' => 'صور الطلب',
        ],
        'megabytes' => 'ميجابايت',
        'save' => 'حفظ',
        'saved' => 'اتحفظت الإعدادات',
    ],
    'media' => [
        'open_document' => 'فتح المستند',
    ],
    'settings_groups' => [
        'media' => 'إعدادات الصور',
    ],
];
