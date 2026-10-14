<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer tokens only (plan 028)
    |--------------------------------------------------------------------------
    |
    | The API has no session: a request is signed in by the token it carries and
    | by nothing else. No address is treated as a first-party page, no session
    | guard is consulted, and the cookie route Sanctum offers pages is not registered.
    |
    */

    'stateful' => [],

    'guard' => [],

    'routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | Every token carries its own expiry, chosen when it is created (7 to 90 days),
    | so no shared lifetime is set here.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token prefix
    |--------------------------------------------------------------------------
    |
    | A recognisable start, so a member, a log filter or a secret scanner can tell
    | what the string is.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'elancer_'),

];
