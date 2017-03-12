<?php
namespace Laravoole\Test;

use Adoy\FastCGI\Client as FastCgiClient;
use WebSocket\Client as WebSocketClient;

class LaravooleTest extends \PHPUnit_Framework_TestCase
{
    protected static $codeCoveraging = false;

    protected $handlers = [
        'SwooleHttp' => [
            'port' => 9050,
            'deal_with_public' => true,
            'gzip' => true,
            'daemonize' => false,
            'worker_num' => 1,
        ],
        'SwooleFastCGI' => [
            'port' => 9051,
            'daemonize' => false,
            'worker_num' => 1,
            'max_request' => 2000,
        ],
        'SwooleWebSocket' => [
            'port' => 9052,
            'daemonize' => false,
            'worker_num' => 1,
        ],
        'WorkermanFastCGI' => [
            'port' => 9053,
            'daemonize' => true,
        ],
    ];

    protected $fastCgiParams = [
        'GATEWAY_INTERFACE' => 'FastCGI/1.0',
        'REQUEST_METHOD' => 'GET',
        'SCRIPT_FILENAME' => '/index.php',
        'SERVER_SOFTWARE' => 'php/fcgiclient',
        'REMOTE_ADDR' => '127.0.0.1',
        'REMOTE_PORT' => '9985',
        'SERVER_ADDR' => '127.0.0.1',
        'SERVER_PORT' => '80',
        'SERVER_NAME' => 'mag-tured',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => 0,
    ];

    public function testSwooleHttpCreate()
    {
        $this->createServer('SwooleHttp');
    }

    /**
     * @depends testSwooleHttpCreate
     */
    public function testSwooleHttp()
    {
        $this->assertRequests('SwooleHttp', 'http', function ($uri) {
            return $this->requestHttp('http://localhost:9050' . $uri);
        });
    }

    /**
     * @depends testSwooleHttpCreate
     */
    public function testSwooleHttpClose()
    {
        $this->closeSwoole('SwooleHttp');
    }

    public function testSwooleFastCgiCreate()
    {
        $this->createServer('SwooleFastCGI');
    }

    /**
     * @depends testSwooleFastCgiCreate
     */
    public function testSwooleFastCgi($client)
    {
        // $this->assertRegExp('~Laravel~', $this->requestFastCgi('http://localhost:9051/'));

        $this->assertRequests('SwooleFastCGI', 'raw', function ($uri) {
            return $this->requestFastCgi('http://localhost:9051' . $uri);
        });
    }

    /**
     * @depends testSwooleFastCgiCreate
     */
    public function testSwooleFastCgiClose()
    {
        $this->closeSwoole('SwooleFastCGI');
    }

    public function testSwooleWebSocketCreate()
    {
        $this->createServer('SwooleWebSocket');
    }

    /**
     * @depends testSwooleWebSocketCreate
     */
    public function testSwooleWebSocket()
    {
        $client = new WebSocketClient('ws://localhost:9052', [
            'headers' => [
                'Sec-Websocket-Protocol' => 'jsonrpc',
            ],
        ]);

        for ($id = 1; $id < 3; $id++) {
            $this->assertJsonStringEqualsJsonString($this->requestWebSocket($client, '/json', $id), json_encode([
                'status' => 200,
                'method' => '/json',
                'result' => json_encode([
                    'object' => ['property' => 'value'],
                    'array' => ['foo', 'bar'],
                ]),
                'id' => $id,
                'error' => null,
            ]));
        }

        $this->assertRequests('SwooleWebSocket', 'http', function ($uri) {
            return $this->requestHttp('http://localhost:9052' . $uri);
        });
    }

    /**
     * @depends testSwooleWebSocketCreate
     */
    public function testSwooleWebSocketClose()
    {
        $this->closeSwoole('SwooleWebSocket');
    }

    public function testWorkermanFastCgiCreate()
    {
        $this->createServer('WorkermanFastCGI');
    }

    /**
     * @depends testWorkermanFastCgiCreate
     */
    public function testWorkermanFastCgi($client)
    {
        // sleep(30);
        $this->assertRequests('WorkermanFastCGI', 'raw', function ($uri) {
            return $this->requestFastCgi('http://localhost:9053' . $uri);
        });
    }

    /**
     * @depends testWorkermanFastCgiCreate
     */
    public function testWorkermanFastCgiClose()
    {
        $this->closeWorkerman('WorkermanFastCGI');
    }

    protected function closeSwoole($mode)
    {
        $pid = (int) @file_get_contents($pidFile = $this->getPidFilePath($mode));
        if ($pid && posix_kill($pid, SIGTERM)) {
            @unlink($pidFile);
        }
    }

    protected function closeWorkerman($mode)
    {
        $pid = (int) @file_get_contents($pidFile = $this->getPidFilePath($mode));
        if ($pid && posix_kill($pid, SIGINT)) {
            @unlink($pidFile);
        }
    }

    protected function assertRequests($mode, $resultType, $callback)
    {
        $this->assertRegExp('~Laravel~', $callback('/'));
        $this->assertStringEndsWith('Laravoole', $callback('/laravoole'));
        $this->assertRegExp('~Laravel - A PHP Framework For Web Artisans~', $callback('/download'));
        if (isset($this->handlers[$mode]['deal_with_public']) && $this->handlers[$mode]['deal_with_public']) {
            $this->assertStringStartsWith('User-agent', $callback('/robots.txt'));
        }

        if (self::$codeCoveraging) {
            $result = $callback('/codeCoverage');
            if ($resultType == 'raw') {
                $result = substr($result, 4 + strpos($result, "\r\n\r\n"));
            }
            $result = json_decode($result, true);
            $this->getTestResultObject()->getCodeCoverage()->append($result);
        }
    }

    protected function requestHttp($url)
    {
        return file_get_contents($url);
    }

    protected function requestFastCgi($url)
    {
        $components = parse_url($url);
        $client = new FastCgiClient($components['host'], $components['port']);
        $params = $this->fastCgiParams;
        $params['REQUEST_URI'] = $components['path'];
        $result = $client->request($params, '');
        $client->setKeepAlive(false);
        return $result;
    }

    protected function requestWebSocket($client, $uri, $id)
    {
        $client->send(json_encode([
            'method' => $uri,
            'params' => [],
            'id' => $id,
        ]));

        return $client->receive();
    }

    protected function createServer($mode)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            // 1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            // 2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $config = $this->getConfig($mode);
        $entry = 'Entry.php';

        if (is_object($codeCoverage = $this->getTestResultObject()->getCodeCoverage())) {
            self::$codeCoveraging = true;
            $virus = function () {
                    return $this->driver;
            };
            $virus = \Closure::bind($virus, $codeCoverage, $codeCoverage);
            $driver = $virus();
            if ($driver instanceof \SebastianBergmann\CodeCoverage\Driver\Xdebug || $driver instanceof \PHP_CodeCoverage_Driver_Xdebug) {
                $entry = 'XdebugEntry.php';
            } else {
                $entry = 'PHPDBGEntry.php';
            }
            $config['base_config']['code_coverage'] = get_class($driver);
        }

        $process = proc_open(PHP_BINARY . ' ' . __DIR__ . "/Entries/{$entry} {$mode}", $descriptorspec, $pipes);

        $this->assertTrue(is_resource($process));
        $write = serialize($config);

        fwrite($pipes[0], $write);
        fclose($pipes[0]);
        // fclose($pipes[1]);

        // ensure server begin
        sleep(1);
        ini_set('default_socket_timeout', 1);
    }

    protected function getPidFilePath($mode)
    {
        return __DIR__ . "/../{$mode}.pid";
    }

    protected function getConfig($mode)
    {
        $wrapper = "Laravoole\\Wrapper\\{$mode}Wrapper";
        $wrapper_file = "src/Wrapper/{$mode}Wrapper.php";
        $handler_config = $this->handlers[$mode];
        $port = $handler_config['port'];
        unset($handler_config['port']);

        $laravooleConfig = include __DIR__ . '/../config/laravoole.php';
        $base_config = $laravooleConfig['base_config'];
        $base_config['callbacks']['bootstraped'][] = [Callbacks::class, 'bootstrapedCallback'];
        $base_config['callbacks']['bootstraping'][] = [Callbacks::class, 'bootstrapingCallback'];
        $wrapper_config = $laravooleConfig['wrapper_config'];
        $wrapper_config['environment_path'] = __DIR__ . '/..';

        $configs = [
            'host' => '127.0.0.1',
            'port' => $port,
            'wrapper_file' => $wrapper_file,
            'wrapper' => $wrapper,
            'pid_file' => $this->getPidFilePath($mode),
            'root_dir' => __DIR__ . '/../vendor/laravel/laravel',
            // for swoole / workerman
            'handler_config' => $handler_config,
            // for wrapper, like http / fastcgi / websocket
            'wrapper_config' => $wrapper_config,
            'base_config' => $base_config,
        ];

        return $configs;
    }

}
