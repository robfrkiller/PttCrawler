<?php
$pg_stime = microtime(true);
include 'vendor/autoload.php';
$config = require 'config.php';
$client = new GuzzleHttp\Client();

use Sunra\PhpSimple\HtmlDomParser;
echo '執行時間：' . date('Y-m-d H:i:s') . PHP_EOL;

// 濾掉已經通知過的文章
if (is_file($config['blocklist_txt'])) {
    $blocklist = file_get_contents($config['blocklist_txt']);
    $block_list = explode(PHP_EOL, $blocklist);
} else {
    $block_list = [];
}

$cookie = new \GuzzleHttp\Cookie\SetCookie();
$cookie->setName('over18');
$cookie->setValue('1');
$cookie->setDomain('www.ptt.cc');

$jar = new \GuzzleHttp\Cookie\CookieJar();
$jar->setCookie($cookie);

$table = $bullet = '';
foreach ($config['urls'] as $url) {

    $res = $client->request('GET', $url['link'], [
        'headers'        => [
            'Accept-Encoding' => 'gzip',
        ],
        'cookies'        => $jar,
    ]);
    if ($res->getStatusCode() !== 200) {
        echo $res->getStatusCode() . ' 失敗: ' . $url['name'] . $url['link'] . PHP_EOL;
        continue;
    }
    $html = HtmlDomParser::str_get_html((string) $res->getBody());
    $findword = $html->find('.title a');

    $list = [];
    foreach ($findword as $e) {
        $alink = strtolower(trim($e->innertext));
        $href = $e->getAttribute('href');
        if (!in_array($href, $block_list) and strposa($alink, $url['keyword'])) {
            $list[] = [
                'href'  => $href,
                'title' => $alink
            ];
            $block_list[] = $href;
        }
    }

    if ($url['newPage'] > 0) {
        $allPage = [
            'index' => $html,
        ];

        if ($url['newPage'] > 1) {
            $newestPage = $html->find('a.wide', 1)->href;
            preg_match('/\/index(\d{1,6})\.html/', $newestPage, $match);
            if (isset($match[1])) {
                $totalPage = $match[1] + 1;
                for ($i = 1; $i < $url['newPage']; ++$i) {
                    $allPage[] = str_replace('index.html', 'index' . ($totalPage - $i) . '.html', $url['link']);
                }
            }
        }

        foreach ($allPage as $key => $val) {
            if ($key === 'index') {
                $newestHtml = $val;
            } else {
                sleep(5);
                $res = $client->request('GET', $val, [
                    'headers'        => [
                        'Accept-Encoding' => 'gzip',
                    ],
                    'cookies'        => $jar,
                ]);
                if ($res->getStatusCode() !== 200) {
                    echo $res->getStatusCode() . ' 失敗: ' . $url['name'] . $val . PHP_EOL;
                    continue;
                }
                $newestHtml = HtmlDomParser::str_get_html((string) $res->getBody());
            }

            $findpush = $newestHtml->find('.nrec span');
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
                if ($push >= $url['push'] and isset($alink->href) and ! in_array($alink->href, $block_list)) {
                    $list[] = [
                        'href'  => $alink->href,
                        'title' => $push . ' 推: ' . $alink->innertext,
                    ];
                    $block_list[] = $alink->href;
                }
            }
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
    if (isset($config['notify']['email'][0])) {
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
    }

    //pushbullet api
    $client = new GuzzleHttp\Client();
    foreach ($config['notify']['pushbullet']['device'] as $push) {
        $req = $client->post('https://api.pushbullet.com/v2/pushes', [
            'headers'   => [
                'Authorization' => $config['notify']['pushbullet']['authorization'],
                'Content-type'  => 'application/json'
            ],
            'body'      => '
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
