<?php
declare(strict_types=1);
return [
    'mode' => 'staging',
    'data_dir' => '/absolute/private/path/.bois-p1-data',
    'admin_token' => 'replace-with-a-long-random-token',
    'allowed_origins' => ['https://example.test'],
    'rate_limit_window' => 60,
    'rate_limit_max' => 20,
    'prices' => [
        'shirt' => 44900,
        'shorts' => 34900,
        'name_print' => 10000,
        'number_print' => 10000,
    ],
];
