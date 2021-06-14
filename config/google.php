<?php

return [
    'smhs' => [
        'client_id' => storage_path('app/google_client/smhs_client_id.txt'),
        'client_secret' => storage_path('app/google_client/smhs_client_secret.json'),
        'chinese_name' => '高雄市立三民高中',
    ],
    'fssh' => [
        'client_id' => storage_path('app/google_client/fssh_client_id.txt'),
        'client_secret' => storage_path('app/google_client/fssh_client_secret.json'),
        'chinese_name' => '國立鳳山高中',
    ],
    'MAPPING' => [
        'smhs.kh.edu.tw' => 'smhs',
        'fssh.khc.edu.tw' => 'fssh',
    ]
];
