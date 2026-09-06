<?php

return [
    'fields' => [
        'headline' => 'headline',
        'bio' => 'bio',
        'location' => 'location',
        'user_id' => 'profile owner',
        'published_at' => 'publication date',
    ],
    'validation' => [
        'string' => 'The :attribute must be text.',
        'max' => 'The :attribute must not exceed :max characters.',
        'missing' => 'The :attribute must not be submitted.',
    ],
];
