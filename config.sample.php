<?php

return [
    'ua'            => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
    'blocklist_txt' => '/var/www/html/PttCrawler/blocklist.txt',
    'urls' => [
        [
            'name'      => '遊戲買賣',
            'link'      => 'https://www.ptt.cc/bbs/Gamesale/index.html',
            'push'      => 99,
            'newPage'   => 0,
            'keyword'   => [
                '測試',
            ]
        ],
    ],

    //通知資訊
    'notify' => [
        'email' => [
            'YOUREMAIL',
        ],
        'pushbullet' => [
            'device'    => [
                [
                    'name'  => 'DEVICE_NAME',
                    'iden'  => 'DEVICE_IDEN'
                ]
            ],
            'authorization' => 'AUTHORIZATION'
        ],
        'slack' => [
            'token'     => 'TOKEN',
            'channel'   => 'CHANNEL',
        ],
    ]
];
