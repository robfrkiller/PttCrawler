<?php
$pg_stime = microtime(true);
include 'vendor/autoload.php';
$config = require 'config.php';
use Sunra\PhpSimple\HtmlDomParser;
echo '執行時間：' . date('Y-m-d H:i:s') . PHP_EOL;

// 濾掉已經通知過的文章
if (is_file($config['blocklist_txt'])) {
	$blocklist = file_get_contents($config['blocklist_txt']);
	$block_list = explode(PHP_EOL, $blocklist);
} else {
	$block_list = [];
}

$table = $bullet = '';
foreach ($config['urls'] as $url) {
	$html = HtmlDomParser::file_get_html($url['link']);
	$findword = $html->find('.title a');

	$list = [];
	foreach ($findword as $e) {
		$alink = strtolower(trim($e->innertext));
		$href = $e->getAttribute('href');
		if (!in_array($href, $block_list) and strposa($alink, $url['keyword'])) {
			$list[] = [
				'href'	=> $href,
				'title'	=> $alink
			];
			$block_list[] = $href;
		}
	}

	$findpush = $html->find('.nrec span');
	foreach ($findpush as $e) {
		$push = $e->innertext;
		if (isset($push{0})) {
			if (isset($push{1}) and $push{1} === 'X') {
				$push = -100;
			} elseif ($push{0} === 'X') {
				$push = $push{1} * -10;
			} elseif ($push === '爆') {
				$push = 100;
			}
		}
		$push = $push + 0;
		$alink = $e->parent()->next_sibling()->next_sibling()->first_child();
		if ($push >= $url['push'] and ! in_array($alink->href, $block_list)) {
			$list[] = [
				'href'	=> $alink->href,
				'title'	=> '推數: ' . $push . ' ' . $alink->innertext,
			];
			$block_list[] = $alink->href;
		}
	}

	if (isset($list[0])) {
		print_r($list);
		$table .= '<table border="1">
					<tr>
						<td colspan="2">' . $url['name'] . '</td>
					</tr>';
		foreach ($list as $b) {
			$table .= '
					<tr>
						<td><a href="https://www.ptt.cc' . $b['href'] . '" target="_blank">link</a></td>
						<td>' . $b['title'] . '</td>
					</tr>';
			$bullet .= $b['title'] . '\r\n' . 'https://www.ptt.cc' . $b['href'] . '\r\n';
		}
		$table .= '</table><br>' . PHP_EOL;
	}
	$name = str_pad($url['name'], 20, ' ', STR_PAD_RIGHT);
	echo $name . ' 寄出 ' . count($list) . ' 個' . PHP_EOL;

	$html->clear();
}

file_put_contents($config['blocklist_txt'], implode(PHP_EOL, $block_list));

if ($table !== '') {
	$mail = new PHPMailer;

	$mail->SMTPDebug = 2;

	$mail->From = 'bot@skynet.com';
	$mail->FromName = '全自動機器人';
	$mail->CharSet = 'UTF-8';
	foreach ($config['notify']['email'] as $email) {
		$mail->addAddress($email);
	}
	$mail->setLanguage('zh');

	$mail->isHTML(true);

	$mail->Subject = 'PTT crawler';
	$mail->Body    = $table;

	if($mail->send()) {
		echo '寄送成功';
	} else {
		echo '寄送失敗，原因：' . $mail->ErrorInfo;
	}
	echo PHP_EOL;

	//pushbullet api
	$client = new GuzzleHttp\Client();
	foreach ($config['notify']['pushbullet']['device'] as $push) {
		$req = $client->post('https://api.pushbullet.com/v2/pushes', [
			'headers'	=> [
				'Authorization'	=> $config['notify']['pushbullet']['authorization'],
				'Content-type'	=> 'application/json'
			],
			'body'		=> '
				{
					"device_iden": "' . $push['iden'] . '",
					"type": "note",
					"title": "PTT crawler",
					"body": "' . $bullet . '"
				}'
		]);
		if ($req->getStatusCode() === 200) {
			$body = $req->getBody();
			$res = json_decode($body->read(9999), 1);
			if ($res['active'] === true) {
				echo $push['name'] . ' push done.' . PHP_EOL;
			}
		}
	}
}

$pg_etime = microtime(true);
echo '執行時間(s):' . ($pg_etime - $pg_stime) . PHP_EOL .
	'========================================================' . PHP_EOL . PHP_EOL;

function strposa($haystack, $needles = [], $offset = 0) {
	foreach($needles as $needle) {
		if (strpos($haystack, $needle, $offset) !== false) {
			return true;
		}
	}
	return false;
}
