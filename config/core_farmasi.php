<?php

return [
    'enabled' => (bool) env('CORE_FARMASI_ENABLED', env('KP_CORE_HTTP_ENABLED', false)),
    'base_url' => env('CORE_FARMASI_URL', env('KP_CORE_BASE_URL')),
    'profile_url' => env('KP_CORE_PROFILE_URL')
        ?: (env('CORE_FARMASI_URL', env('KP_CORE_BASE_URL')) ? rtrim((string) env('CORE_FARMASI_URL', env('KP_CORE_BASE_URL')), '/') . '/profile' : null),
    'storage_public_path' => env('KP_CORE_STORAGE_PUBLIC_PATH'),
    'app_code' => env('CORE_FARMASI_APP_CODE', env('KP_CORE_APP_CODE', 'kppspa-farmasi')),
    'client_id' => env('CORE_FARMASI_CLIENT_ID', env('KP_CORE_CLIENT_ID')),
    'client_secret' => env('CORE_FARMASI_CLIENT_SECRET', env('KP_CORE_CLIENT_SECRET')),
    'timeout' => (int) env('CORE_FARMASI_TIMEOUT', env('KP_CORE_TIMEOUT', 10)),
    'connect_timeout' => (int) env('CORE_FARMASI_CONNECT_TIMEOUT', env('KP_CORE_CONNECT_TIMEOUT', 3)),
    'verify_ssl' => (bool) env('CORE_FARMASI_VERIFY_SSL', env('KP_CORE_VERIFY_SSL', true)),
    'read_mode' => env('KP_CORE_READ_MODE', 'legacy'),
    'fail_silently' => (bool) env('KP_CORE_FAIL_SILENTLY', true),
];
