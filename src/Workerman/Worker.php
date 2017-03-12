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
	    // @codeCoverageIgnoreStart
	    self::resetStd();
	    self::monitorWorkers();
	    // @codeCoverageIgnoreEnd
	} // @codeCoverageIgnore

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
