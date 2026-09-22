<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // La app nativa no envia Origin: CORS solo aplica a navegadores. Se listan
    // el propio sitio y, en local, Expo web (8081). Nunca '*' con credenciales.
    'allowed_origins' => array_values(array_filter([
        env('APP_URL'),
        env('APP_ENV') === 'local' ? 'http://localhost:8081' : null,
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Tokens Bearer: no hacen falta cookies entre origenes.
    'supports_credentials' => false,

];
