<?php
namespace Laravoole;

class App extends \Illuminate\Foundation\Application
{
    public function isProviderLoaded($name)
    {
        return isset($this->loadedProviders[$name]);
    }
}
