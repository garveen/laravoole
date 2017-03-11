#!/usr/bin/env php
<?php

$handlers = [
    'SwooleHttp' => [
        'port' => 9050,
        'deal_with_public' => true,
        'gzip' => false,
        'daemonize' => true,
    ],
    'SwooleFastCGI' => [
        'port' => 9051,
        'daemonize' => true,
    ],
    'SwooleWebsocket' => [
        'port' => 9052,
        'daemonize' => true,
    ],
    'WorkermanFastCGI' => [
        'port' => 9053,
        'daemonize' => true,
    ],
];

$wrapper_config = [
    'websocket_default_protocol' => 'jsonrpc',

    'websocket_protocols' => [
        'jsonrpc' => Laravoole\WebsocketCodec\JsonRpc::class,
    ],

    'environment_path' => __DIR__,
];

foreach ($handlers as $mode => $handler_config) {
    $wrapper = "Laravoole\\Wrapper\\{$mode}Wrapper";
    $wrapper_file = "src/Wrapper/{$mode}Wrapper.php";
    $port = $handler_config['port'];
    unset($handler_config['port']);

    $configs = [
        'host' => '127.0.0.1',
        'port' => $port,
        'wrapper_file' => $wrapper_file,
        'wrapper' => $wrapper,
        'pid_file' => __DIR__ . "/{$mode}.pid",
        'root_dir' => __DIR__ . '/vendor/laravel/laravel',
        // for swoole / workerman
        'handler_config' => $handler_config,
        // for wrapper, like http / fastcgi / websocket
        'wrapper_config' => $wrapper_config,
    ];

    $handle = popen('/usr/bin/env php ' . __DIR__ . '/src/Entry.php', 'w');
    fwrite($handle, serialize($configs));
    fclose($handle);
}

// wait for services start
sleep(1);

ini_set('default_socket_timeout', 1);

$errors = [];

function check($output)
{
    global $errors;
    if (!strpos($output, 'Laravel')) {
        ob_start();
        debug_print_backtrace(2);
        $errors[] = ob_get_clean() . $output;
        echo "failed\n";
    } else {
        echo "checked\n";
    }
}

require 'vendor/autoload.php';
check(@file_get_contents('http://localhost:9050'));

use WebSocket\Client as WebSocketClient;

try {
    $client = new WebSocketClient("ws://localhost:9052");
    $client->send(json_encode([
        'method' => '/',
        'params' => ['hello' => 'world'],
        'id' => 1,
    ]));

    check($client->receive());
} catch (Exception $e) {
    check('');
}

check(@file_get_contents('http://localhost:9052'));

use Adoy\FastCGI\Client as FastCgiClient;

$fastCgiParams = [
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
try {
    $client = new FastCgiClient('localhost', '9051');
    check($client->request($fastCgiParams, ''));
} catch (Exception $e) {
    check('');
}

try {
    $client = new FastCgiClient('localhost', '9053');
    check($client->request($fastCgiParams, ''));
} catch (Exception $e) {
    check('');
}

$pid = (int) @file_get_contents($pidFile = __DIR__ . "/WorkermanFastCGI.pid");
if ($pid && posix_kill($pid, SIGINT)) {
    @unlink($pidFile);
}

unset($handlers['WorkermanFastCGI']);

foreach ($handlers as $mode => $handler_config) {
    $pid = (int) @file_get_contents($pidFile = __DIR__ . "/{$mode}.pid");
    if ($pid && posix_kill($pid, SIGTERM)) {
        @unlink($pidFile);
    }
}

if ($errors) {
    foreach ($errors as $error) {
        echo $error;
    }
    return 1;
}
return 0;
