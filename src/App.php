<?php
namespace Laravoole;

class App extends \Illuminate\Foundation\Application
{
    public function isProviderLoaded($name)
    {
        foreach ($this->serviceProviders as $key => $value) {
        }
        return isset($this->loadedProviders[$name]);
    }
}
