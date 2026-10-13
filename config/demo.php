<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo entry
    |--------------------------------------------------------------------------
    |
    | The login and register pages offer a visitor one prepared account per
    | role, to look around without registering. A button appears only where
    | its account exists and is usable. Nothing here is a credential.
    |
    */

    'enabled' => (bool) env('DEMO_SIGN_IN', true),

    'accounts' => [
        'client' => env('DEMO_CLIENT_EMAIL', 'client-01@demo.elancer.test'),
        'freelancer' => env('DEMO_FREELANCER_EMAIL', 'freelancer-01@demo.elancer.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The demo world
    |--------------------------------------------------------------------------
    |
    | Every account on this reserved address domain is a demo account. Demo
    | accounts and real members never hire each other, and nobody can
    | register on the domain.
    |
    | A session opened from the demo buttons may change things among demo
    | accounts once a super administrator has saved a starting point, because
    | a reset can then take the changes back. Until then, or with
    | DEMO_ACTIONS=false, such a session is view-only (RestrictDemoSession).
    |
    | A reset runs when a visitor enters and nobody has changed the demo for
    | "idle_minutes", or when it has stayed changed for "max_hours".
    |
    */

    'domain' => env('DEMO_DOMAIN', 'demo.elancer.test'),

    'actions' => (bool) env('DEMO_ACTIONS', true),

    'reset' => [
        'idle_minutes' => (int) env('DEMO_RESET_IDLE_MINUTES', 30),
        'max_hours' => (int) env('DEMO_RESET_MAX_HOURS', 12),
    ],

];
