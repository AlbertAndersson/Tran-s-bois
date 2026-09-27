<?php
declare(strict_types=1);

return [
    'mode' => 'staging',
    'data_dir' => '/absolute/private/path/.bois-p1-data',
    'admin_token' => 'replace-with-a-long-random-token',
    'allowed_origins' => ['https://example.test'],
    'allowed_teams' => [
        'P9','F9','P13','F14','P16','Skridsko- & bandyskola 26/27',
    ],
    'allowed_sizes' => ['128','140','152','164','XS','S'],
    'product_id' => 'match-kit-knatte',
    'product_label' => 'Matchställ Knatte',
    'supplier_code' => '',
    'order_period_id' => '2026-27-matchstall',
    'order_period_label' => 'Matchställ 2026/27',
    'rate_limit_window' => 60,
    'rate_limit_max' => 20,
    'prices' => [
        'shirt' => 44900,
        'shorts' => 34900,
        'name_print' => 10000,
        'number_print' => 10000,
    ],
];
