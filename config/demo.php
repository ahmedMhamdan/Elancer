<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo entry
    |--------------------------------------------------------------------------
    |
    | The login and register pages offer a visitor one prepared account per
    | role, to look around without registering. A button appears only where
    | its account exists and is usable, and a session opened this way is
    | view-only (RestrictDemoSession). Nothing here is a credential.
    |
    */

    'enabled' => (bool) env('DEMO_SIGN_IN', true),

    'accounts' => [
        'client' => env('DEMO_CLIENT_EMAIL', 'client-01@demo.elancer.test'),
        'freelancer' => env('DEMO_FREELANCER_EMAIL', 'freelancer-01@demo.elancer.test'),
    ],

];
