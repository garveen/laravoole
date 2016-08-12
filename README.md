#Laravoole

Laravel on Swoole Or Workerman

10x faster than php-fpm

##Depends On

<table>
	<tr>
		<td>php</td><td>>=5.5.16</td>
	</tr>
	<tr>
		<td>laravel/framework</td><td>5.1.* | 5.2.*</td>
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
---------

```shell
 composer require garveen/laravoole
```

##Usage
-------

```shell
 vendor/bin/laravoole start | stop | reload | restart | quit
```

##Config
--------

In .env , use LARAVOOLE_* to config Laravoole.

###FastCGI or HTTP
------------------

```INI
 LARAVOOLE_MODE=SwooleHttp
 LARAVOOLE_MODE=SwooleFastCGI
 LARAVOOLE_MODE=WorkermanFastCGI
```

Default is set to SwooleHttp, or you can also use other protocols.


###pid_file
-----------

```INI
 LARAVOOLE_PID_FILE=/path/to/laravoole.pid
```

###deal\_with\_public
---------------------

Use this ***ONLY*** when developing

```INI
 LARAVOOLE_DEAL_WITH_PUBLIC=true
```

###Host and Port

```INI
 LARAVOOLE_HOST=0.0.0.0
 LARAVOOLE_PORT=9050
```

Default host is 127.0.0.1:9050

###Swoole
---------

As an example, if want to set worker_num to 8, just add a line:

```INI
 LARAVOOLE_WORKER_NUM=8
```

See Swoole's document:

[简体中文](http://wiki.swoole.com/wiki/page/274.html)

[English](https://cdn.rawgit.com/tchiotludo/swoole-ide-helper/dd73ce0dd949870daebbf3e8fee64361858422a1/docs/classes/swoole_server.html#method_set)

###Workerman

As an example, if want to set the count of workers to 8, just add a line:

```INI
 LARAVOOLE_COUNT=8
```

See Workerman's document:

[简体中文](http://doc3.workerman.net/worker-development/property.html)

[English](http://wiki.workerman.net/Workerman_documentation#Properties)

##Work with nginx
-----------------

```Nginx
server {
	listen       80;
	server_name  localhost;

	root /path/to/laravel/public;

	location / {
            try_files $uri $uri/ @laravoole;
            index  index.html index.htm index.php;
        }

	# proxy
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
}
```

#License
[MIT](LICENSE)
