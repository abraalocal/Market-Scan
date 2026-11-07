<?php

return [
    /*
    | Abraa category names → abraa.com category ids. Fill the ids from abraa.com (Yasser).
    | Unknown categories from the AI map to "General".
    */
    'categories' => [
        'Hair Care' => env('ABRAA_CAT_HAIR_CARE'),
        'Skin Care' => env('ABRAA_CAT_SKIN_CARE'),
        'Body Care' => env('ABRAA_CAT_BODY_CARE'),
        'Fragrance' => env('ABRAA_CAT_FRAGRANCE'),
        'Makeup' => env('ABRAA_CAT_MAKEUP'),
        'General' => env('ABRAA_CAT_GENERAL'),
    ],
    'fallback_category' => 'General',
    'join_url' => env('ABRAA_JOIN_URL', 'https://abraa.com/join/'),
];
