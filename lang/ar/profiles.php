<?php

return [
    'fields' => [
        'headline' => 'العنوان المهني',
        'bio' => 'النبذة التعريفية',
        'location' => 'الموقع',
        'user_id' => 'مالك الملف الشخصي',
        'published_at' => 'تاريخ النشر',
    ],
    'validation' => [
        'string' => 'يجب أن يكون حقل :attribute نصًا.',
        'max' => 'يجب ألا يتجاوز حقل :attribute عدد :max حرفًا.',
        'missing' => 'لا يُسمح بإرسال حقل :attribute.',
    ],
];
