<?php
namespace Laravoole;

require __DIR__ . '/../vendor/autoload.php';

function env($key, $default = null)
{
    if ($default instanceof \Closure) {
        return $default();
    }
    return $default;
}

function storage_path()
{
    return '';
}
