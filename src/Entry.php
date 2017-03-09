<?php

$input = file_get_contents('php://stdin');

$configs = unserialize($input);

require_once $configs['root_dir'] . '/bootstrap/autoload.php';

$argv = $configs['argv'];
$server = new Laravoole\Server($configs['mode']);
$server->start(
    $configs['host'],
    $configs['port'],
    $configs['pid_file'],
    $configs['root_dir'],
    $configs['handler_config'],
    $configs['wrapper_config']
);
