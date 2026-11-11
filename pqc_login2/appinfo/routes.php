<?php

return [
    'routes' => [

        [
            'name' => 'challenge#getChallenge',
            'url' => '/challenge',
            'verb' => 'GET'
        ],

        [
            'name' => 'registration#index',
            'url' => '/register',
            'verb' => 'GET'
        ],

        [
            'name' => 'registration#register',
            'url' => '/register',
            'verb' => 'POST'
        ],

        [
            'name' => 'verify#verify',
            'url' => '/verify',
            'verb' => 'POST'
        ],

        // 🔥 NEW LOGOUT ROUTE
        [
            'name' => 'logout#destroy',
            'url' => '/destroy-session',
            'verb' => 'POST'
        ],

        // 🛡️ RECOVERY ROUTES
        [
            'name' => 'recovery#index',
            'url' => '/recovery',
            'verb' => 'GET'
        ],
        [
            'name' => 'recovery#verify',
            'url' => '/recovery/verify',
            'verb' => 'POST'
        ],
        [
            'name' => 'recovery#rebind',
            'url' => '/recovery/rebind',
            'verb' => 'POST'
        ]

    ]
];
