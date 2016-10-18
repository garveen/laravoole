<?php

namespace Laravoole\Illuminate;

use Illuminate\Http\Request as IlluminateRequest;
use Closure;

class Request extends IlluminateRequest
{
    protected $laravooleInfo;

    public function setLaravooleInfo($info)
    {
        if (!isset($this->laravooleInfo)) {
            $this->laravooleInfo = (object) $info;
        } else {
            foreach ($info as $k => $v) {
                $this->laravooleInfo->$k = $v;
            }
        }
    }

    public function getLaravooleInfo()
    {
        return $this->laravooleInfo;
    }

    public function forceNextMessageRoute($method, $params = [])
    {
        $this->laravooleInfo->nextMessageRoute = [
            'method' => $method,
            'params' => $params,
            'previous' => $this,
        ];
    }

}
