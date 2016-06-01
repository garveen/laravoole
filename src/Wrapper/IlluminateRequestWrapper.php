<?php

namespace Laravoole\Wrapper;

use Illuminate\Http\Request;
use Closure;

class IlluminateRequestWrapper extends Request
{
    public $laravooleIssetUserResolver = false;

    public function setLaravooleUserResolver(Closure $callback)
    {
        $this->laravooleIssetUserResolver = $callback;
        return parent::setUserResolver($callback);
    }

}
