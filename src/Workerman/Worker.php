<?php
namespace Laravoole\Workerman;

class Worker extends \Workerman\Worker
{
	public static function runAll()
	{
	    self::checkSapiEnv();
	    self::init();
	    self::parseCommand();
	    self::daemonize();
	    self::initWorkers();
	    self::installSignal();
	    self::saveMasterPid();
	    self::forkWorkers();
	    self::displayUI();
	    self::resetStd();
	    self::monitorWorkers();
	}

    protected static function parseCommand()
    {
        global $argv;
        $argv = [
            __FILE__,
            'start',
        ];
        parent::parseCommand();
    }
}
