<?php

namespace Laravoole\Wrapper;

use Illuminate\Http\Request;

class IlluminateRequestWrapper extends Request
{
	public $laravooleBackups;
	public function __set($name, $value)
	{
		$laravooleBackups[$name = $value];
		$this->$name = $value;
		return $value;
	}
}
