<?php

class HttpProxyServer
{
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
            HttpProxyServer::$clients[$fd] = $client;
            $client->on('close', function ($cli) use ($fd)
            {
                //echo "http[{$cli->sock}] client close\n";
                self::removeClient($fd);
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

$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
$serv->set(array('worker_num' => 4));

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
        $client->post($req->server['request_uri'], $req->rawContent(), function ($cli) use ($req, $resp)
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