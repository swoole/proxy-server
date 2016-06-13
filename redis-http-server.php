<?php
class HttpRedisServer
{
    static $frontendCloseCount = 0;
    static $backends = array();
    static $serv;
}

$serv = new swoole_http_server('127.0.0.1', 9511, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
//$serv->set(array('worker_num' => 8));

$serv->on('Close', function ($serv, $fd, $reactorId)
{
    HttpRedisServer::$frontendCloseCount++;
    echo HttpRedisServer::$frontendCloseCount . "\tfrontend[{$fd}] close\n";
    //清理掉后端连接
    if (isset(HttpRedisServer::$backends[$fd]))
    {
        $redis = HttpRedisServer::$backends[$fd];
        $redis->close();
    }
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    $fd = $req->fd;
    if (!isset(HttpRedisServer::$backends[$fd]))
    {
        $redis = new swoole_redis;
        HttpRedisServer::$backends[$req->fd] = $redis;
        $redis->on('close', function ($cli) use ($fd)
        {
            unset(HttpRedisServer::$backends[$fd]);
            echo "redis-client#{$fd}] is closed\n";
        });
        $redis->connect('127.0.0.1', 6379, function ($redis, $result) use ($resp)
        {
            $redis->get("key", function($redis, $res) use ($resp){
                $resp->end("<h1>redis_result=".$res."</h1>");
            });
        });
    }
    else
    {
        $redis = HttpRedisServer::$backends[$req->fd];
        $redis->get("key", function($redis, $res) use ($resp){
            $resp->end("<h1>redis_result=".$res."</h1>");
        });
    }
});

HttpRedisServer::$serv = $serv;
$serv->start();
