<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SupportManager API
    |--------------------------------------------------------------------------
    |
    | Configuration for the SupportManager backend that client apps talk to.
    | "base_url" is the application root (NOT including /api/v1); the API
    | client appends the versioned path itself. Auth endpoints live on the
    | central domain, e.g. https://app.support-manager.co.uk/api/v1/auth/login.
    |
    */

    'base_url' => env('SUPPORTMANAGER_API_URL', 'https://app.support-manager.co.uk'),

    /*
    | Sent as "device_name" when issuing tokens so users can identify and
    | revoke this device from their account. Tokens are named per device.
    */
    'device_name' => env('SUPPORTMANAGER_DEVICE_NAME', 'SupportManager Mobile'),

    /*
    | Request + connect timeouts, in seconds.
    */
    'timeout' => (int) env('SUPPORTMANAGER_API_TIMEOUT', 30),

    'connect_timeout' => (int) env('SUPPORTMANAGER_API_CONNECT_TIMEOUT', 10),

    /*
    | How many seconds before access-token expiry a proactive refresh will
    | renew the pair. Hosts that run a background refresh (e.g. the mobile
    | app's scheduler) read this to renew shortly before expiry rather than
    | waiting for the first reactive 401, which remains the source of truth.
    */
    'refresh_skew' => (int) env('SUPPORTMANAGER_REFRESH_SKEW', 1800),

    /*
    | How long locally cached profile avatar bytes are considered fresh. The
    | Platform returns short-lived signed URLs, so client apps store the bytes
    | and refresh them opportunistically rather than relying on the URL.
    */
    'avatar_cache_ttl' => (int) env('SUPPORTMANAGER_AVATAR_CACHE_TTL', 86400),

];
