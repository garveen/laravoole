<?php
namespace Laravoole\Illuminate;

class Application extends \Illuminate\Foundation\Application
{
    public function isProviderLoaded($name)
    {
        return isset($this->loadedProviders[$name]);
    }
}
