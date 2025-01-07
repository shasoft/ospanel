<?php
// https://docs.guzzlephp.org/en/stable/quickstart.html

namespace Shasoft\OsPanel\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Cookie\CookieJar;
use Shasoft\OsPanel\TraitOsPanel;

class MainTest extends TestCase
{
    use TraitOsPanel;
    // Куки
    protected ?CookieJar $cookieJar = null;
    protected ?Client $client = null;
    //
    public function getUri(string $path, callable $cb): void
    {
        $host = $this->osPanelHostCreate(__DIR__ . '/../../test-site');
        if ($this->osPanelHostHas($host)) {
            $uri = 'https://' . $host . $path;
            //echo $uri . PHP_EOL;
            $cb($uri);
        }
    }
    //
    public function setUp(): void
    {
        parent::setUp();
        $this->cookieJar = new CookieJar();
        $this->client = new Client(
            [
                RequestOptions::TIMEOUT => 300.0,
                RequestOptions::COOKIES => $this->cookieJar,
                RequestOptions::HTTP_ERRORS => false
            ]
        );
    }
    public function tearDown(): void
    {
        $this->client = null;
        $this->cookieJar = null;
        parent::tearDown();
    }

    public function testGetShasoftCom(): void
    {
        $response = $this->client->get('http://shasoft.com');
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testDoc(): void
    {
        // Создадим домен и привяжем к нему директорию сайта
        $host = $this->osPanelHostCreate(__DIR__ . '/../../test-site');
        // Домен активен для выполнения запросов?
        if ($this->osPanelHostHas($host)) {
            // Сформируем URI для выполнения запроса
            $uri = 'https://' . $host . '/get';
            // Выполним запрос
            $response = $this->client->get($uri);
            // Обработаем результата запроса
            self::assertEquals(200, $response->getStatusCode());
        }
    }

    public function testGet(): void
    {
        $this->getUri('/get', function (string $uri) {
            $response = $this->client->get($uri);
            self::assertEquals(200, $response->getStatusCode());
            self::assertEquals('GetOk', $response->getBody()->getContents());
        });
    }

    public function testGet404(): void
    {
        $this->getUri('/get', function (string $uri) {
            $response = $this->client->post($uri);
            self::assertEquals(404, $response->getStatusCode());
        });
    }

    public function testPost(): void
    {
        $this->getUri('/post', function (string $uri) {
            $response = $this->client->post($uri);
            self::assertEquals(200, $response->getStatusCode());
            self::assertEquals('PostOk', $response->getBody()->getContents());
        });
    }

    public function testPost404(): void
    {
        $this->getUri('/post', function (string $uri) {
            $response = $this->client->get($uri);
            self::assertEquals(404, $response->getStatusCode());
        });
    }

    public function testPostFormParams(): void
    {
        $this->getUri('/post2', function (string $uri) {
            $a = '1';
            $b = '2';
            $x = ':';
            $response = $this->client->post($uri, [
                'form_params' => [
                    'a' => $a,
                    'b' => $b,
                    'x' => $x
                ]
            ]);
            self::assertEquals(200, $response->getStatusCode());
            self::assertEquals($a . $x . $b, $response->getBody()->getContents());
        });
    }
}
