<?php

namespace Laravoole;

use Illuminate\Support\Facades\Facade;

class LaravooleFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravoole.server';
    }
}
