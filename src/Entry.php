<?php
$input = file_get_contents('php://stdin');
spl_autoload_register(function ($class) {
    if (is_file($file = __DIR__ . '/' . substr(strtr($class, '\\', '/'), 10) . '.php')) {
        require $file;
        return true;
    }
    if (is_file($file = __DIR__ . '/../tests/' . substr(strtr($class, '\\', '/'), 15) . '.php')) {
        require $file;
        return true;
    }
    return false;
});
$configs = unserialize($input);

$server = new Laravoole\Server($configs['wrapper'], $configs['wrapper_file']);

$server->start($configs);
