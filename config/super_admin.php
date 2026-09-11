<?php

return [
    // Optional local bootstrap for migrate:fresh --seed. Never commit credentials.
    'email' => env('ELANCER_SUPER_ADMIN_EMAIL'),
    'password_hash' => env('ELANCER_SUPER_ADMIN_PASSWORD_HASH'),
];
