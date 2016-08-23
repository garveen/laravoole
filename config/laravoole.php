<?php
return [
    'base_config' => [
        'host' => env('LARAVOOLE_HOST', '127.0.0.1'),
        'port' => env('LARAVOOLE_PORT', 9050),
        'pid_file' => env('LARAVOOLE_PID_FILE', storage_path('/logs/laravoole.pid')),
        'root_dir' => base_path(),
        'deal_with_public' => env('LARAVOOLE_DEAL_WITH_PUBLIC', false),
        'gzip' => env('LARAVOOLE_GZIP', 1),
        'gzip_min_length' => env('LARAVOOLE_GZIP_MIN_LENGTH', 1024),
        'mode' => env('LARAVOOLE_MODE', function () {
            if (extension_loaded('swoole')) {
                return 'SwooleHttp';
            } elseif (class_exists('Workerman\Worker')) {
                return 'WorkermanFastCGI';
            } else {
            	return;
            }
        }),
        'server' => env('LARAVOOLE_SERVER', 'Laravoole'),
    ],
    'handler_config' => [
        'max_request' => env('LARAVOOLE_MAX_REQUEST', 2000),
        'daemonize' => env('LARAVOOLE_DAEMONIZE', 1),
    ],
    'wrapper_config' => [
        'websocket_default_protocol' => env('LARAVOOLE_WEBSOCKET_DEFAULT_PROTOCOL', 'jsonrpc'),
        'websocket_protocols' => [
            'jsonrpc' => 'Laravoole\WebsocketCodec\JsonRpc2',
        ],
    ],

];
