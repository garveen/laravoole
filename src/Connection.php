<?php
namespace Laravoole;
use Illuminate\Http\Request as IlluminateRequest;

class Connection
{
	public function __construct($swooleRequest)
	{
		// $this->swooleRequest
		$get = isset($request->get) ? $request->get : array();
		$post = [];
		$cookie = [];
		$server = isset($request->server) ? $request->server : array();
		$header = isset($request->header) ? $request->header : array();

		$new_header = [];
		foreach ($header as $key => $value) {
		    $new_header['http_' . $key] = $value;
		}
		$server = array_merge($server, $new_header);

        $this->illuminate_request = new IlluminateRequest($get, $post, []/* attributes */, $cookie, [], $server);

	}

}
