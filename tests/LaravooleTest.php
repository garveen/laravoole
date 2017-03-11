<?php
namespace Laravoole\Test;

use PHPUnit\Framework\TestCase;

use Adoy\FastCGI\Client as FastCgiClient;
use WebSocket\Client as WebSocketClient;

class LaravooleTest extends TestCase
{
    protected static $codeCoveraging = false;

    protected $handlers = [
        'SwooleHttp' => [
            'port' => 9050,
            'deal_with_public' => true,
            'gzip' => true,
            'daemonize' => false,
        ],
        'SwooleFastCGI' => [
            'port' => 9051,
            'daemonize' => false,
        ],
        'SwooleWebsocket' => [
            'port' => 9052,
            'daemonize' => false,
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

    public function testCreateSwooleHttp()
    {
        $this->createServer('SwooleHttp');
    }

    /**
     * @depends testCreateSwooleHttp
     */
    public function testSwooleHttp()
    {
        $this->assertRequests('http', function ($uri) {
            return $this->requestHttp('http://localhost:9050' . $uri);
        });
    }

    /**
     * @depends testCreateSwooleHttp
     */
    public function testCloseSwooleHttp()
    {
        $this->closeSwoole('SwooleHttp');
    }

    public function testCreateSwooleFastCgi()
    {
        $this->createServer('SwooleFastCGI');
    }

    /**
     * @depends testCreateSwooleFastCgi
     */
    public function testSwooleFastCgi($client)
    {
        // $this->assertRegExp('~Laravel~', $this->requestFastCgi('http://localhost:9051/'));

        $this->assertRequests('raw', function ($uri) {
            return $this->requestFastCgi('http://localhost:9051' . $uri);
        });
    }

    /**
     * @depends testCreateSwooleFastCgi
     */
    public function testCloseSwooleFastCgi()
    {
        $this->closeSwoole('SwooleFastCGI');
    }

    public function testCreateSwooleWebSocket()
    {
        $this->createServer('SwooleWebsocket');
    }

    /**
     * @depends testCreateSwooleWebSocket
     */
    public function testSwooleWebSocket()
    {
        $this->assertRequests('http', function ($uri) {
            return $this->requestHttp('http://localhost:9052' . $uri);
        });
        $client = new WebSocketClient('ws://localhost:9052');

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
    }

    /**
     * @depends testCreateSwooleWebSocket
     */
    public function testCloseSwooleWebSocket()
    {
        $this->closeSwoole('SwooleWebsocket');
    }

    public function testCreateWorkermanFastCgi()
    {
        $this->createServer('WorkermanFastCGI');
    }

    /**
     * @depends testCreateWorkermanFastCgi
     */
    public function testWorkermanFastCgi($client)
    {
        // sleep(30);
        $this->assertRequests('raw', function ($uri) {
            return $this->requestFastCgi('http://localhost:9053' . $uri);
        });
    }

    /**
     * @depends testCreateWorkermanFastCgi
     */
    public function testCloseWorkermanFastCgi()
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

    protected function assertRequests($resultType, $callback)
    {
        $this->assertRegExp('~Laravel~', $callback('/'));
        $this->assertStringEndsWith('Laravoole', $callback('/laravoole'));
        $this->assertRegExp('~Laravel - A PHP Framework For Web Artisans~', $callback('/download'));
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

        $process = proc_open(PHP_BINARY . ' ' . __DIR__ . '/../src/Entry.php ' . $mode, $descriptorspec, $pipes);

        $this->assertTrue(is_resource($process));
        $write = serialize($this->getConfig($mode));

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

        if (is_object($codeCoverage = $this->getTestResultObject()->getCodeCoverage())) {
            self::$codeCoveraging = true;
            $virus = function () {
                return [
                    'driver' => get_class($this->driver),
                    'check' => $this->shouldCheckForDeadAndUnused,
                ];
            };
            $virus = \Closure::bind($virus, $codeCoverage, $codeCoverage);
            $base_config['callbacks']['bootstraping'][] = [Callbacks::class, 'bootstrapingCallback'];
            $base_config['code_coverage'] = $virus();
        }

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
