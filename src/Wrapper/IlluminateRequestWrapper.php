<?php

namespace Laravoole\Wrapper;

use Illuminate\Http\Request;
use Closure;

class IlluminateRequestWrapper extends Request
{
    protected $laravooleInfo;

    public function setLaravooleInfo($info)
    {
        if (!$this->laravooleInfo) {
            $this->laravooleInfo = (object) $info;
        } else {
            foreach ($info as $k => $v) {
                $this->laravooleInfo->k = $v;
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
