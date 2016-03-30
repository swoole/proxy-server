<?php

class HttpProxyServer
{
    static $frontendCloseCount = 0;
    static $backendCloseCount = 0;
    static protected $clients = array();
    /**
     * @param $fd
     * @return swoole_http_client
     */
    static function getClient($fd)
    {
        if (!isset(HttpProxyServer::$clients[$fd]))
        {
            $client = new swoole_http_client('127.0.0.1', 80);
            $client->set(array('keep_alive' => 1));
            HttpProxyServer::$clients[$fd] = $client;
            $client->on('close', function ($cli) use ($fd)
            {
                self::$backendCloseCount ++;
                self::removeClient($fd);
                echo self::$backendCloseCount."\tbackend[{$cli->sock}] close\n";
            });
        }
        return HttpProxyServer::$clients[$fd];
    }

    /**
     * @param $fd
     */
    static function removeClient($fd)
    {
        if (isset(HttpProxyServer::$clients[$fd]))
        {
            unset(HttpProxyServer::$clients[$fd]);
        }
    }
}

$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
$serv->set(array('worker_num' => 1));

$serv->on('Close', function ($serv, $fd, $reactorId)
{
    HttpProxyServer::$frontendCloseCount++;
    echo HttpProxyServer::$frontendCloseCount . "\tfrontend[{$fd}] close\n";
    HttpProxyServer::removeClient($fd);
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    if ($req->server['request_method'] == 'GET')
    {
        $client = HttpProxyServer::getClient($req->fd);
        $client->get($req->server['request_uri'], function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
        });
    }
    elseif ($req->server['request_method'] == 'POST')
    {
        $client = HttpProxyServer::getClient($req->fd);
        $postData = $req->rawContent();
        $client->post($req->server['request_uri'], $postData, function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
        });
    }
    else
    {
        $resp->status(405);
        $resp->end("method not allow.");
    }
});

$serv->start();