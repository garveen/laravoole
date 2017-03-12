#Laravoole

Laravel on Swoole Or Workerman

10x faster than php-fpm

[![Latest Stable Version](https://poser.pugx.org/garveen/laravoole/v/stable)](https://packagist.org/packages/garveen/laravoole)
[![Total Downloads](https://poser.pugx.org/garveen/laravoole/downloads)](https://packagist.org/packages/garveen/laravoole)
[![Latest Unstable Version](https://poser.pugx.org/garveen/laravoole/v/unstable)](https://packagist.org/packages/garveen/laravoole)
[![License](https://poser.pugx.org/garveen/laravoole/license)](https://packagist.org/packages/garveen/laravoole)
[![Build Status](https://travis-ci.org/garveen/laravoole.svg?branch=master)](https://travis-ci.org/garveen/laravoole)

##Depends On

<table>
	<tr>
		<td>php</td><td>>=5.5.16</td>
	</tr>
	<tr>
		<td>laravel/laravel</td><td>^ 5.1</td>
	</tr>
</table>

##Suggests

<table>
	<tr>
		<td>php</td><td>>=7.0.0</td>
	</tr>
	<tr>
		<td>ext-swoole</td><td>>=1.7.21</td>
	</tr>
	<tr>
		<td>workerman/workerman</td><td>>=3.0</td>
	</tr>
</table>


##Install


To get started, add laravoole to you composer.json file and run `composer update`:

```
"garveen/laravoole": "^0.5.0"
```

or just run shell command:

```shell
 composer require garveen/laravoole
```

Once composer done its job, you need to register Laravel service provider, in your config/app.php:

```
'providers' => [
    ...
    Laravoole\LaravooleServiceProvider::class,
],
```

**Notice: You should NOT use file session handler, because it is not stable at this environement. Use redis or other handler instead.**

##Usage

```shell
php artisan laravoole [start | stop | reload | reload_task | restart | quit]
```

##Migrations

###Upgrade to 0.4

Event names has changed:

- `laravoole.on_request` => `laravoole.requesting`
- `laravoole.on_requested` => `laravoole.requested`
- `laravoole.swoole.websocket.on_close` => `laravoole.swoole.websocket.closing`

##Config

To generate `config/laravoole.php`:

```shell
php artisan vendor:publish --provider="Laravoole\LaravooleServiceProvider"
```

Most of things can be configured with `.env`, and you should use `LARAVOOLE_{UPPER_CASE}` format, for example,

```php
[
    'base_config' => [
        'host' => '0.0.0.0',
    ]
]
```

is equals with

```env
LARAVOOLE_HOST=0.0.0.0
```

##Events

You can handle events by editing `EventServiceProvider`:

```php
public function boot()
{
    parent::boot();
    \Event::listen('laravoole.requesting', function ($request) {
        \Log::info($request->segments());
    });
}
```

- `laravoole.requesting`(`Illuminate\Http\Request`)
- `laravoole.requested`(`Illuminate\Http\Request`, `Illuminate\Http\Response`)
- `laravoole.swoole.websocket.closing`(`Laravoole\Request`, int `$fd`)

##base_config

This section configures laravoole itself.

###mode

`SwooleHttp` uses swoole to response http requests

`SwooleFastCGI` uses swoole to response fastcgi requests (just like php-fpm)

`SwooleWebSocket` uses swoole to response websocket requests **AND** http requests

`WorkermanFastCGI` uses workerman to response fastcgi requests (just like php-fpm)

####user defined wrappers

You can make a new wrapper implements `Laravoole\Wrapper\ServerInterface`, and put its full class name to `mode`.

###pid_file

Defines a file that will store the process ID of the main process.

###deal\_with\_public

When using Http mode, you can turn on this option to let laravoole send static resources to clients. Use this ***ONLY*** when developing.

###host and port

Default `host` is `127.0.0.1`, and `port` is `9050`

##handler_config

This section configures the backend, e.g. `swoole` or `workerman`.

###Swoole

As an example, if want to set worker_num to 8, you can set `.env`:

```INI
 LARAVOOLE_WORKER_NUM=8
```

or set `config/laravoole.php`:
```php
[
    'handler_config' => [
        'worker_num' => 8,
    ]
]
```

See Swoole's document:

[简体中文](http://wiki.swoole.com/wiki/page/274.html)

[English](https://cdn.rawgit.com/tchiotludo/swoole-ide-helper/dd73ce0dd949870daebbf3e8fee64361858422a1/docs/classes/swoole_server.html#method_set)

###Workerman

As an example, if want to set worker_num to 8, you can set `.env`:

```INI
 LARAVOOLE_COUNT=8
```

or set `config/laravoole.php`:
```php
[
    'handler_config' => [
        'count' => 8,
    ]
]
```

See Workerman's document:

[简体中文](http://doc3.workerman.net/worker-development/property.html)

[English](http://wiki.workerman.net/Workerman_documentation#Properties)

##Websocket Usage

###Subprotocols

See Mozilla's Document: [Writing WebSocket server](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#Subprotocols)

The default subprotocol is [jsonrpc](http://json-rpc.org/), but has some different: `params` is an object, and two more properties:

`status` as HTTP status code

`method` is the same as request's method


You can define your own subprotocol, by implements `Laravoole\WebsocketCodec\CodecInterface` and add to `config/laravoole.php`.

###Client Example:

```html
<!DOCTYPE html>
<meta charset="utf-8" />
<title>WebSocket Test</title>
<style>
p{word-wrap: break-word;}
tr:nth-child(odd){background-color: #ccc}
tr:nth-child(even){background-color: #eee}
</style>
<h2>WebSocket Test</h2>
<table><tbody id="output"></tbody></table>
<script>
    var wsUri = "ws://localhost:9050/websocket";
    var protocols = ['jsonrpc'];
    var output = document.getElementById("output");

    function send(message) {
        websocket.send(message);
        log('Sent', message);
    }

    function log(type, str) {
        str = str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        output.insertAdjacentHTML('beforeend', '<tr><td>' + type + '</td><td><p>' + htmlEscape(str) + '</p></td></tr>');
    }

    websocket = new WebSocket(wsUri, protocols);
    websocket.onopen = function(evt) {
        log('Status', "Connection opened");
        send(JSON.stringify({method: '/', params: {hello: 'world'},  id: 1}));
        setTimeout(function(){ websocket.close() },1000)
    };
    websocket.onclose = function(evt) { log('Status', "Connection closed") };
    websocket.onmessage = function(evt) { log('<span style="color: blue;">Received</span>', evt.data) };
    websocket.onerror = function(evt) {  log('<span style="color: red;">Error</span>', evt.data) };
</script>
</html>
```


##Work with nginx

```Nginx
server {
    listen       80;
    server_name  localhost;

    root /path/to/laravel/public;

    location / {
            try_files $uri $uri/ @laravoole;
            index  index.html index.htm index.php;
        }

    # http
    location @laravoole {
        proxy_set_header   Host $host:$server_port;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_http_version 1.1;

        proxy_pass http://127.0.0.1:9050;
    }

    # fastcgi
    location @laravoole {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9050;
    }

    # websocket
    # send close if there has not an upgrade header
    map $http_upgrade $connection_upgrade {
        default upgrade;
        '' close;
    }
    location /websocket {
        proxy_connect_timeout 7d;
        proxy_send_timeout 7d;
        proxy_read_timeout 7d;
        proxy_pass http://127.0.0.1:9050;
        proxy_http_version 1.1;
        proxy_set_header Upgrade    $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }
}
```

#License
[MIT](LICENSE)
