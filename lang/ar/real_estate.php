<?php

declare(strict_types=1);

return [
    'purposes' => ['sale' => 'للبيع', 'rent' => 'للإيجار'],
    'types' => [
        'apartment' => 'شقة', 'villa' => 'فيلا', 'duplex' => 'دوبلكس', 'house' => 'بيت', 'land' => 'أرض',
        'shop' => 'محل', 'office' => 'مكتب إداري', 'warehouse' => 'مخزن', 'factory' => 'مصنع',
    ],
    'finishings' => ['unfinished' => 'على الطوب', 'semi_finished' => 'نص تشطيب', 'finished' => 'متشطب', 'luxury' => 'سوبر لوكس'],
    'rent_periods' => ['monthly' => 'شهري', 'yearly' => 'سنوي'],
    'platform_name' => 'عاوز إيه',

    'notifications' => [
        'approve' => ['title' => 'إعلانك اتنشر', 'body' => 'عقارك «:title» بقى ظاهر للناس.'],
        'reject' => ['title' => 'إعلانك محتاج تعديل', 'body' => 'عقارك «:title» ما اتنشرش. شوف السبب، عدّل وابعته تاني.'],
        'take_down' => ['title' => 'إعلانك اتشال', 'body' => 'عقارك «:title» مبقاش ظاهر للناس. شوف السبب، عدّل وابعته تاني.'],
    ],

    'attributes' => [
        'purpose' => 'الغرض', 'type' => 'نوع العقار', 'title' => 'العنوان', 'description' => 'الوصف', 'price' => 'السعر',
        'rent_period' => 'مدة الإيجار', 'area_m2' => 'المساحة', 'bedrooms' => 'عدد الغرف', 'bathrooms' => 'عدد الحمامات',
        'floor' => 'الدور', 'furnished' => 'مفروش', 'finishing' => 'التشطيب', 'district_id' => 'المنطقة',
        'address_note' => 'وصف العنوان', 'lat' => 'خط العرض', 'lng' => 'خط الطول', 'radius_m' => 'نطاق البحث',
        'photos' => 'الصور', 'photos.*' => 'الصورة', 'submit' => 'إرسال للمراجعة', 'price_min' => 'أقل سعر',
        'price_max' => 'أعلى سعر', 'area_min' => 'أقل مساحة', 'area_max' => 'أكبر مساحة', 'bedrooms_min' => 'أقل عدد غرف',
        'brokered' => 'بوساطة المنصة', 'sort' => 'الترتيب', 'type.*' => 'نوع العقار', 'district_id.*' => 'المنطقة',
    ],
];
