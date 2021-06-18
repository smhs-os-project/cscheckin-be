<?php

return [
    'smhs' => [
        'client_id' => storage_path('app/google_client/smhs_client_id.key'),
        'client_secret' => storage_path('app/google_client/smhs_client_secret.json'),
        'chinese_name' => '高雄市立三民高中',
    ],
    'fssh' => [
        'client_id' => storage_path('app/google_client/fssh_client_id.key'),
        'client_secret' => storage_path('app/google_client/fssh_client_secret.json'),
        'chinese_name' => '國立鳳山高級中學',
    ],
    'common' => [
        'client_id' => storage_path('app/google_client/general_client_id.key'),
        'client_secret' => storage_path('app/google_client/general_client_secret.json'),
        'chinese_name' => 'Google 個人或其他帳戶',
    ],
    'MAPPING' => [
        'smhs-kh-edu-tw' => 'smhs',
        'fssh-khc-edu-tw' => 'fssh',
        '*' => 'common',
    ]
];
