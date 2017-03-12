<?php
namespace Laravoole\Test;

class Callbacks
{
    public static function bootstrapingCallback($wrapper)
    {
        if(isset($wrapper->base_config['code_coverage'])) {
            $driver = new $wrapper->base_config['code_coverage'];
            $wrapper->codeCoverage = $driver;
        }
        putenv('APP_KEY=base64:5lEhduX0I3FzAvKTTcVy3PyQ18356CgNpFWVlTzDlcg=');
        putenv('LARAVOOLE_DEAL_WITH_PUBLIC=true');
    }

    public static function bootstrapedCallback($wrapper)
    {
        (new \Laravoole\LaravooleServiceProvider($wrapper->getApp()))->register();

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
