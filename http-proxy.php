<?php
$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_BASE);

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    $client = new swoole_http_client('127.0.0.1', 80);
    if ($req->server['request_method'] == 'GET')
    {
        $req->client = $client;
        $client->get($req->server['request_uri'], function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
            $req->client = null;
        });
    }
    elseif ($req->server['request_method'] == 'POST')
    {
        $req->client = $client;
        $client->post($req->server['request_uri'], $req->rawContent(), function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
            $req->client = null;
        });
    }
    else
    {
        $resp->status(405);
        $resp->end("method not allow.");
    }
});

$serv->start();