<?php
namespace Laravoole\Protocol;

use Laravoole\Request;
use Laravoole\Response;
use Laravoole\Parser;
use Exception;

trait FastCGI
{

    protected static $lowMark = 8; // initial value of the minimal amout of bytes in buffer
    protected static $highMark = 0xFFFFFF; // initial value of the maximum amout of bytes in buffer
    public static $timeout = 180;

    static $HEADER_LENGTH = 8;

    static $FCGI_BEGIN_REQUEST = 1;
    static $FCGI_ABORT_REQUEST = 2;
    static $FCGI_END_REQUEST = 3;
    static $FCGI_PARAMS = 4;
    static $FCGI_STDIN = 5;
    static $FCGI_STDOUT = 6;
    static $FCGI_STDERR = 7;
    static $FCGI_DATA = 8;
    static $FCGI_GET_VALUES = 9;
    static $FCGI_GET_VALUES_RESULT = 10;
    static $FCGI_UNKNOWN_TYPE = 11;

    static $FCGI_RESPONDER = 1;
    static $FCGI_AUTHORIZER = 2;
    static $FCGI_FILTER = 3;

    static $FCGI_KEEP_CONN = 1;

    protected static $roles = [
        1 => 'FCGI_RESPONDER',
        2 => 'FCGI_AUTHORIZER',
        3 => 'FCGI_FILTER',
    ];

    static $STATE_HEADER = 0;
    static $STATE_BODY = 1;
    static $STATE_PADDING = 2;

    static $requests = [];
    static $connections = [];

    protected static function close($fd)
    {
        throw new Exception(__CLASS__ . "::close MUST be implemented", 1);
    }

    protected static function send($fd, $content)
    {
        throw new Exception(__CLASS__ . "::send MUST be implemented", 1);
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

        if (!isset(static::$connections[$fd])) {
            static::$connections[$fd]['buff'] = '';
        } else {
            $data = static::$connections[$fd]['buff'] . $data;
        }
        if (!isset(static::$connections[$fd]['length'])) {
            $pack = substr($data, 4, 3);
            $info = unpack('ncontentLength/CpaddingLength', $pack);
            static::$connections[$fd]['length'] = 8 + $info['contentLength'] + $info['paddingLength'];
        }

        if (static::$connections[$fd]['length'] <= strlen($data)) {
            $result = static::parseRecord($data);

            static::$connections[$fd]['buff'] = $result['remainder'];
            static::$connections[$fd]['length'] = null;
        } else {
            static::$connections[$fd]['buff'] = $data;
            return;
        }

        if (count($result['records']) == 0) {
            fwrite(STDOUT, "Bad Request. data length: " . strlen($data));
            static::close($fd);
            return;
        }
        foreach ($result['records'] as $record) {
            $rid = $record['requestId'];
            $type = $record['type'];

            if ($type == static::$FCGI_BEGIN_REQUEST) {
                $req = static::$requests[$rid] = new Request($fd);
                $req->id = $rid;
                $u = unpack('nrole/Cflags', $record['contentData']);
                $req->attrs->role = static::$roles[$u['role']];
                $req->attrs->flags = $u['flags'];
                static::$connections[$fd]['request'] = $req;
            } elseif (isset(static::$requests[$rid])) {
                $req = static::$requests[$rid];
            } else {
                fwrite(STDOUT, "Unexpected FastCGI-record #. Request ID: $fd\n");
                return;
            }

            if ($type == static::$FCGI_ABORT_REQUEST) {
                $req->destoryTempFiles();

                unset(static::$requests[$rid]);
                unset(static::$connections[$fd]);

            } elseif ($type == static::$FCGI_PARAMS) {
                if (!$record['contentLength']) {

                    $req->finishParams();
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
            } elseif ($type === static::$FCGI_STDIN) {
                if ($record['contentLength']) {
                    $req->setRawContent($record['contentData']);
                    continue;
                } else {
                    $req->finishRawContent();
                }
            }

            if ($req->attrs->paramsDone && $req->attrs->inputDone) {
                $header = [];
                foreach ($req->server as $k => $v) {
                    if (strncmp($k, 'HTTP_', 5) === 0) {
                        $header[strtr(ucwords(strtolower(substr($k, 5)), '_'), '_', '-')] = $v;
                    }
                }

                $req->header = $header;
                Parser::parseCookie($req);
                Parser::parseBody($req);

                $response = new Response(static::class, $req);
                static::onRequest($req, $response);
                // destory tmp files
                $req->destoryTempFiles();

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
        $chunksize = 65520;
        do {
            if (strlen($out) > $chunksize) {
                while (($ol = strlen($out)) > 0) {
                    $l = min($chunksize, $ol);
                    if (static::sendChunk($req, substr($out, 0, $l)) === false) {
                        fwrite(STDOUT, "send response failed.\n");
                        break 2;
                    }
                    $out = substr($out, $l);
                }
            } elseif (static::sendChunk($req, $out) === false) {
                fwrite(STDOUT, "send response failed.\n");
                break;
            }
        } while (false);
        static::endRequest($req, 0, 0);

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
        $paddingLength = 8 - strlen($chunk) % 8;
        $payload = "\x01" // protocol version
         . "\x06" // record type (STDOUT)
         . pack('nnC', $req->id, strlen($chunk), $paddingLength) // id, content length, padding length
         . "\x00" // reserved
         . $chunk // content
         . str_repeat("\0", $paddingLength);

        return static::send($req->fd, $payload);
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
        $content = pack('NC', $appStatus, $protoStatus) // app status, protocol status
         . "\x00\x00\x00";
        $paddingLength = 8 - strlen($content) % 8;

        $payload = "\x01" // protocol version
         . "\x03" // record type (END_REQUEST)
         . pack('nnC', $req->id, strlen($content), $paddingLength) // id, content length, padding length
         . "\x00" // reserved
         . $content // content
         . str_repeat("\0", $paddingLength);

        static::send($req->fd, $payload);
        $req->destoryTempFiles();
        unset(static::$requests[$req->id]);

        if ($protoStatus === -1 || !($req->attrs->flags & static::$FCGI_KEEP_CONN)) {
            static::close($req->fd);
        }
    }

    public static function onClose($serv, $fd, $from_id)
    {
        if (isset(static::$connections[$fd]['request'])) {
            $request = static::$connections[$fd]['request'];
            $request->destoryTempFiles();

            unset(static::$requests[$request->id]);
        }
        unset(static::$connections[$fd]);

    }

}
