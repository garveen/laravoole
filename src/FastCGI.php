<?php
namespace Laravoole;

use swoole_server;
use Exception;

class FastCGI extends Http
{
    protected static $lowMark = 8; // initial value of the minimal amout of bytes in buffer
    protected static $highMark = 0xFFFFFF; // initial value of the maximum amout of bytes in buffer
    public static $timeout = 180;

    const HEADER_LENGTH = 8;

    const FCGI_BEGIN_REQUEST = 1;
    const FCGI_ABORT_REQUEST = 2;
    const FCGI_END_REQUEST = 3;
    const FCGI_PARAMS = 4;
    const FCGI_STDIN = 5;
    const FCGI_STDOUT = 6;
    const FCGI_STDERR = 7;
    const FCGI_DATA = 8;
    const FCGI_GET_VALUES = 9;
    const FCGI_GET_VALUES_RESULT = 10;
    const FCGI_UNKNOWN_TYPE = 11;

    const FCGI_RESPONDER = 1;
    const FCGI_AUTHORIZER = 2;
    const FCGI_FILTER = 3;

    protected static $roles = [
        self::FCGI_RESPONDER => 'FCGI_RESPONDER',
        self::FCGI_AUTHORIZER => 'FCGI_AUTHORIZER',
        self::FCGI_FILTER => 'FCGI_FILTER',
    ];

    const STATE_HEADER = 0;
    const STATE_BODY = 1;
    const STATE_PADDING = 2;

    static $requests = [];

    public static function init($config, $swoole_settings)
    {
        // override
        $config['deal_with_public'] = false;
        parent::init($config, $swoole_settings);
    }

    public static function start()
    {
        static::$server = new swoole_server(static::$host, static::$port);

        if (!empty(static::$swoole_settings)) {
            static::$server->set(static::$swoole_settings);
        }
        static::$server->on('start', [static::class, 'onServerStart']);
        static::$server->on('receive', [static::class, 'onReceive']);
        static::$server->on('shutdown', [static::class, 'onServerShutdown']);
        static::$server->on('WorkerStart', [static::class, 'onWorkerStart']);

        require __DIR__ . '/Mime.php';

        static::$server->start();
    }

    public static function parseRecord($data)
    {
        $records = array();
        while (strlen($data)) {
            if (strlen($data) < 8) {
                /**
                 * We don't have a full header
                 */
                break;
            }
            $header = substr($data, 0, 8);
            $record = unpack('Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength/Creserved', $header);
            $recordlength = 8 + $record['contentLength'] + $record['paddingLength'];
            $record['contentData'] = substr($data, 8, $record['contentLength']);

            if (strlen($data) < $recordlength) {
                /**
                 * We don't have a full record.
                 */
                break;
            }
            $records[] = $record;
            $data = substr($data, $recordlength);
        }
        return array('records' => $records, 'remainder' => $data);
    }

    public static function onReceive($serv, $fd, $from_id, $data)
    {
        $result = static::parseRecord($data);
        if (count($result['records']) == 0) {
            fwrite(STDOUT, "Bad Request. data=" ."\nresult: " . var_export($result, true));
            static::$server->close($fd);
            return;
        }
        foreach ($result['records'] as $record) {
            $rid = $record['requestId'];
            $type = $record['type'];
            if ($type == self::FCGI_BEGIN_REQUEST) {
                $u = unpack('nrole/Cflags', $record['contentData']);
                $req = new Request($fd, $rid);
                $req->attrs->role = self::$roles[$u['role']];
                $req->attrs->flags = $u['flags'];
                static::$requests[$rid] = $req;
            } elseif (isset(static::$requests[$rid])) {
                $req = static::$requests[$rid];
            } else {
                fwrite(STDOUT, 'Unexpected FastCGI-record #. Request ID: ' . $rid . '.');
                return;
            }

            if ($type === self::FCGI_ABORT_REQUEST) {
                $req->abort();
            } elseif ($type === self::FCGI_PARAMS) {
                if ($record['contentData'] === '') {
                    if (!isset($req->server['REQUEST_TIME'])) {
                        $req->server['REQUEST_TIME'] = time();
                    }
                    if (!isset($req->server['REQUEST_TIME_FLOAT'])) {
                        $req->server['REQUEST_TIME_FLOAT'] = microtime(true);
                    }
                    $req->attrs->paramsDone = true;
                } else {
                    $p = 0;
                    while ($p < $record['contentLength']) {
                        if (($namelen = ord($record['contentData']{ $p})) < 128) {
                            ++$p;
                        } else {
                            $u = unpack('Nlen', chr(ord($record['contentData']{ $p}) & 0x7f) . substr($record['contentData'], $p + 1, 3));
                            $namelen = $u['len'];
                            $p += 4;
                        }

                        if (($vlen = ord($record['contentData']{ $p})) < 128) {
                            ++$p;
                        } else {
                            $u = unpack('Nlen', chr(ord($record['contentData']{ $p}) & 0x7f) . substr($record['contentData'], $p + 1, 3));
                            $vlen = $u['len'];
                            $p += 4;
                        }

                        $req->server[substr($record['contentData'], $p, $namelen)] = substr($record['contentData'], $p + $namelen, $vlen);
                        $p += $namelen + $vlen;
                    }
                }
            } elseif ($type === self::FCGI_STDIN) {
                if ($record['contentLength'] !== 0) {
                    $req->setRawContent($record['contentData']);
                    continue;
                } else {
                    $req->attrs->inputDone = true;
                }
            }

            if ($req->attrs->paramsDone && $req->attrs->inputDone) {
                $header = [];
                foreach ($req->server as $k => $v) {
                    if (strncmp($k, 'HTTP_', 5) === 0) {
                        $header[strtr(ucwords(strtolower(substr($k, 5)), '_'), '_', '-')] = $v;
                    }
                }
                $req->body = $req->rawContent();
                $req->header = $header;
                Parser::parseCookie($req);
                Parser::parseBody($req);

                $response = new Response(static::class, $req);
                static::onRequest($req, $response);

            }

        }
    }

    /**
     * Handles the output from downstream requests.
     * @param object Request.
     * @param string The output.
     * @return boolean Success
     */
    public static function response($req, $out)
    {
        $cs = $chunksize = 8192;
        do {
            if (strlen($out) > $chunksize) {
                while (($ol = strlen($out)) > 0) {
                    $l = min($chunksize, $ol);
                    if (static::sendChunk($req, substr($out, 0, $l)) === false) {
                        fwrite(STDOUT, "send response failed.");
                        break 2;
                    }
                    $out = substr($out, $l);
                }
            } elseif (static::sendChunk($req, $out) === false) {
                fwrite(STDOUT, "send response failed.");
                break;
            }
        } while (false);
        static::endRequest($req, 0, -1);

        return true;
    }

    /**
     * Sends a chunk
     * @param $req
     * @param $chunk
     * @return bool
     */
    public static function sendChunk($req, $chunk)
    {
        return static::$server->send($req->fd,
            "\x01" // protocol version
             . "\x06" // record type (STDOUT)
             . pack('nn', $req->id, strlen($chunk)) // id, content length
             . "\x00" // padding length
             . "\x00" // reserved
        ) && static::$server->send($req->fd, $chunk); // content
    }

    /**
     * Handles the output from downstream requests.
     * @param $req
     * @param $appStatus
     * @param $protoStatus
     * @return void
     */
    public static function endRequest($req, $appStatus = 0, $protoStatus = 0)
    {
        $c = pack('NC', $appStatus, $protoStatus) // app status, protocol status
         . "\x00\x00\x00";

        static::$server->send($req->fd,
            "\x01" // protocol version
             . "\x03" // record type (END_REQUEST)
             . pack('nn', $req->id, strlen($c)) // id, content length
             . "\x00" // padding length
             . "\x00" // reserved
             . $c // content
        );

        if ($protoStatus === -1) {
            static::$server->close($req->fd);
        }
    }

    public static function onClose($serv, $fd, $req)
    {
        unset(static::$requests[$fd]);
    }
}
