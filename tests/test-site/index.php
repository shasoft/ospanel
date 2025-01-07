<?php

use Shasoft\WebBrowser\WebBrowser;

require_once __DIR__ . '/../../vendor/autoload.php';

//echo phpinfo();

/*
$a = 1.7817466E+38;
$b = 1.7817465947118E+38;
s_dd(
    ColumnReal::compare($a, $b),
    ColumnReal::compare(1.7817467E+38, 1.7817465947118E+38),
    ColumnReal::compare(1.7817466E+38, 1.7817465957118E+38)
);
//*/

s_dump_run(function () {

    $browser = new WebBrowser('http://' . 'shasoft-test.ru');
    $response = $browser->request('/post')->post();
    s_dd($response, $response->content());
    /*
    $browser = new WebBrowser('http://shasoft-cmg-phpunit.ru');
    $response = $browser->request('/')->get();
    s_dump($response, $browser, $response->content());

    $response = $browser->request('/auth/email/register')->post([
        'password' => '12345678',
        'password_confirm' => '12345678',
        'email' => 'test-web-browser@shasoft.com'
    ]);
    s_dump($response, $browser, $response->hasJson(), $response->json());
	//*/

    $browser = new WebBrowser('https://vk.com/');
    $response = $browser->request('/')->get();
    s_dump($response, $browser);
});
