<?php

return [
	'blocklist_txt' => '/var/www/html/PttCrawler/blocklist.txt',
	'urls' => [
		[
			'name'		=> '遊戲買賣',
			'link'		=> 'https://www.ptt.cc/bbs/Gamesale/index.html',
			'keyword'	=> [
				'測試',
			]
		],
	],

	//通知資訊
	'notify' => [
		'email' => 'YOUREMAIL',
		'pushbullet' => [
			'device'	=> [
				[
					'name'	=> 'DEVICE_NAME',
					'iden'	=> 'DEVICE_IDEN'
				]
			],
			'authorization' => 'AUTHORIZATION'
		]
	]
];
