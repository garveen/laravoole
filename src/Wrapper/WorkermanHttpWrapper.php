<?php
namespace Laravoole\Wrapper;

use Laravoole\Workerman\Worker;
use Laravoole\Illuminate\Request;
use Laravoole\Response;

class WorkermanHttpWrapper extends Workerman implements ServerInterface
{
    use HttpTrait;

    public function __construct($host, $port)
    {
        parent::__construct($host, $port);
        $this->server = new Worker("http://{$host}:{$port}");
    }

    public function start()
    {
        $this->on('Receive', [$this, 'onRequest']);
        return parent::start();
    }

    public function onWorkerStart($worker)
    {
        parent::onWorkerStart($worker);
        $this->accept_gzip = config('laravoole.base_config.gzip');
    }

    public function onRequest($connection, $data)
    {
        $request = new Request($data['get'], $data['post'], []/* attributes */, $data['cookie'], $data['files'], $data['server']);
        $request->setLaravooleInfo(['workermanConnection' => $connection]);
        $response = new Response($this, $request);
        // provide response callback
        $illuminateResponse = parent::handleRequest($request);
        return $this->handleResponse($response, $illuminateResponse, $request->header('Accept-Encoding', ''));
    }

    public function endResponse($response, $content)
    {
    	$connection = $response->request->getLaravooleInfo()->workermanConnection;
        if (!is_string($content)) {
        	$response->content = file_get_contents($content());
        } else {
        	$response->content = $content;
        }
        $rawContent = $response->getRawContent();
    	$connection->maxSendBufferSize = strlen($rawContent);
        $connection->close($rawContent, true);
    }
}
