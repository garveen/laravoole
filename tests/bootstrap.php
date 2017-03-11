<?php
namespace Laravoole;

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
