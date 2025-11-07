<?php

use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;


class HttpProxyServer
{
    protected static array $upstreams = [];
    public static Server $server;

    const string BACKEND_HOST = '127.0.0.1';
    const int BACKEND_PORT = 80;

    public static function getClient($fd): Client
    {
        if (!isset(self::$upstreams[$fd])) {
            $client = new Client(self::BACKEND_HOST, self::BACKEND_PORT);
            $client->set(array('keep_alive' => true));
            self::$upstreams[$fd] = $client;
        }
        return self::$upstreams[$fd];
    }

    public static function unsetClient($fd): void
    {
        unset(self::$upstreams[$fd]);
    }
}

$serv = new Server('127.0.0.1', 9510, SWOOLE_BASE);

$serv->on('Close', function ($serv, $fd, $reactorId) {
    HttpProxyServer::unsetClient($fd);
});

$serv->on('Request', function (request $req, response $resp) {
    // HTTP does not support concurrency. When processing a request and there is no response, the client will not send a new request
    $client = HttpProxyServer::getClient($req->fd);
    if ($req->server['request_method'] == 'GET') {
        $rs = $client->get($req->server['request_uri']);
    } elseif ($req->server['request_method'] == 'POST') {
        $rs = $client->post($req->server['request_uri'], $req->getContent());
    } else {
        $resp->status(405);
        $resp->end("Method Not Allow");
        return;
    }
    if ($rs) {
        $resp->end($client->body);
    } else {
        $resp->status(502);
        $resp->end("Bad Gateway");
    }
});

HttpProxyServer::$server = $serv;
$serv->start();
