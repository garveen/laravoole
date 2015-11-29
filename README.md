#Laravoole

Laravel on Swoole

##Depends On
<table>
	<tr>
		<td>php</td><td>>=5.5.9</td>
	</tr>
	<tr>
		<td>ext-swoole</td><td>>=1.7.19</td>
	</tr>
	<tr>
		<td>laravel/framework</td><td>5.1.*</td>
	</tr>
</table>

##Install

 composer require acabin/laravoole

##Usage

 vendor/bin/laravoole start | stop | reload | restart | quit


##Config

In .env , use LARAVOOLE_* to config Laravoole.

###pid_file

 LARAVOOLE_PID_FILE=/path/to/laravoole.pid

###gzip

 LARAVOOLE_GZIP=1

level is in the range from 1 to 9, bigger is compress harder and use more CPU time.

 LARAVOOLE_GZIP_MIN_LENGTH=1024

Sets the minimum length of a response that will be gzipped.

###deal_with_public

Use this *ONLY* when developing

 LARAVOOLE_DEAL_WITH_PUBLIC=true

###Swoole

Example:

 LARAVOOLE_HOST=0.0.0.0

Default host is 127.0.0.1:9050

See Swoole's document:

[Chinese](http://wiki.swoole.com/wiki/page/274.html)

[English](https://cdn.rawgit.com/tchiotludo/swoole-ide-helper/dd73ce0dd949870daebbf3e8fee64361858422a1/docs/classes/swoole_server.html#method_set)

[MIT](LICENSE)
