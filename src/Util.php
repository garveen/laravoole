<?php
namespace Laravoole;

use ReflectionClass;

class Util
{

    public static function checkWrapper($wrapper)
    {
        if (class_exists($wrapper)) {
            $class = $wrapper;
        } else {
            $class = "Laravoole\\Wrapper\\{$wrapper}Wrapper";
        }

        $refl = new ReflectionClass($class);
        if (!array_key_exists('Laravoole\Wrapper\ServerInterface', $refl->getInterfaces())) {
            throw new \Exception('laravoole wrapper should be an instance of Laravoole\Wrapper\ServerInterface');
        }

        return $class;
    }

}
