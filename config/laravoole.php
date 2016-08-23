<?php
return [
    // for laravoole itself
    'base_config' => [
        'host' => env('LARAVOOLE_HOST', '127.0.0.1'),
        'port' => env('LARAVOOLE_PORT', 9050),

        // this file storages the pid of laravoole
        'pid_file' => env('LARAVOOLE_PID_FILE', storage_path('/logs/laravoole.pid')),

        // when using Http mode, you can turn on this option to let laravoole send static resources to clients
        // ONLY use this when developing
        'deal_with_public' => env('LARAVOOLE_DEAL_WITH_PUBLIC', false),

        // enable gzip
        'gzip' => env('LARAVOOLE_GZIP', 1),

        'gzip_min_length' => env('LARAVOOLE_GZIP_MIN_LENGTH', 1024),

        // laravoole modes:
        // SwooleHttp        uses swoole to response http requests
        // SwooleFastCGI     uses swoole to response fastcgi requests (just like php-fpm)
        // SwooleWebSocket   uses swoole to response websocket requests and http requests
        // WorkermanFastCGI  uses workerman to response fastcgi requests (just like php-fpm)
        'mode' => env('LARAVOOLE_MODE', function () {
            if (extension_loaded('swoole')) {
                return 'SwooleHttp';
            } elseif (class_exists('Workerman\Worker')) {
                return 'WorkermanFastCGI';
            } else {
                return;
            }
        }),

        // response header server
        'server' => env('LARAVOOLE_SERVER', 'Laravoole'),
    ],
    // for swoole / workerman
    'handler_config' => [
        'max_request' => env('LARAVOOLE_MAX_REQUEST', 2000),
        'daemonize' => env('LARAVOOLE_DAEMONIZE', 1),
    ],
    'wrapper_config' => [
        // websocket default protocol
        'websocket_default_protocol' => env('LARAVOOLE_WEBSOCKET_DEFAULT_PROTOCOL', 'jsonrpc'),

        'websocket_protocols' => [
            'jsonrpc' => Laravoole\WebsocketCodec\JsonRpc::class,
        ],
    ],

];
