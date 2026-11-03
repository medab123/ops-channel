<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trust anchor
    |--------------------------------------------------------------------------
    |
    | Base64-encoded Ed25519 PUBLIC key (32 bytes) of the one caller allowed to
    | run commands here. Only the public half belongs in an application: being
    | able to verify a signature is not being able to make one, so this is not a
    | credential. The private half never leaves the caller.
    |
    | There is deliberately NO default. With no key configured the package
    | registers no routes at all, so installing it grants nothing until an
    | application opts in by naming a key it trusts.
    |
    */

    'public_key' => env('OPS_CHANNEL_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Handshake
    |--------------------------------------------------------------------------
    */

    'handshake' => [
        // Seconds a freshly issued challenge remains redeemable.
        'challenge_ttl' => (int) env('OPS_CHANNEL_CHALLENGE_TTL', 30),

        // Allowed clock drift (seconds) between the signed timestamp and now.
        'timestamp_tolerance' => (int) env('OPS_CHANNEL_TIMESTAMP_TOLERANCE', 30),

        // Cache key namespace for pending challenges.
        'cache_prefix' => env('OPS_CHANNEL_CACHE_PREFIX', 'ops-channel:handshake:'),

        // Which cache store holds pending challenges. Null uses the default
        // store. It MUST survive between two requests: the challenge is written
        // when the session opens and read back when the signature arrives, so an
        // `array` store refuses every call, and a per-process store refuses any
        // call whose two requests land on different workers.
        'cache_store' => env('OPS_CHANNEL_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Two endpoints are registered under this prefix: POST {prefix}/session
    | issues a challenge, POST {prefix}/sync runs the signed command.
    |
    */

    'routes' => [
        'enabled' => (bool) env('OPS_CHANNEL_ROUTES_ENABLED', true),

        'prefix' => env('OPS_CHANNEL_ROUTE_PREFIX', 'api/v1/internal/system'),

        // Applied to the challenge endpoint, which is the only one an
        // unauthenticated caller can reach. The command endpoint is gated by the
        // signature itself.
        'session_throttle' => env('OPS_CHANNEL_SESSION_THROTTLE', '30,1'),

        // Extra middleware for both endpoints, e.g. an IP allow-list.
        'middleware' => [],
    ],

];
