<?php
namespace Laravoole\Test;

class Callbacks
{
    public static function bootstrapingCallback($wrapper)
    {
        $driver = new $wrapper->base_config['code_coverage'];
        $wrapper->codeCoverage = $driver;
    }

    public static function bootstrapedCallback($wrapper)
    {
        $route = $wrapper->getApp()->router;
        $route->get('/laravoole', function () {
            return 'Laravoole';
        });
        $route->get('/download', function () {
            return response()->download('index.php');
        });

        $route->get('/json', function () {
            return [
                'object' => ['property' => 'value'],
                'array' => ['foo', 'bar'],
            ];
        });

        $route->get('/codeCoverage', function() use ($wrapper) {
            return $wrapper->codeCoverage->stop();
        });
    }
}
