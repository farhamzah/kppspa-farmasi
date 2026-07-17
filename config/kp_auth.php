<?php

return [
    'mode' => env('KP_AUTH_MODE', 'core_http'),

    'core_bridge_allowed_roles' => [
        'admin-kp',
        'mahasiswa',
        'dosen',
        'koordinator-kp',
        'pembimbing-dalam',
        'pembimbing-lapangan',
        'penguji',
    ],
];
