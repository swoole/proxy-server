<?php
class HttpRedisServer
{
    static $frontendCloseCount = 0;
    static $frontends = array();
    static $serv;

    /**
     * @param $fd
     * @return swoole_http_client
     */
    static function getClient($fd)
    {

        return HttpRedisServer::$frontends[$fd];
    }
}

$serv = new swoole_http_server('127.0.0.1', 9511, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
//$serv->set(array('worker_num' => 8));

$serv->on('Close', function ($serv, $fd, $reactorId)
{
    HttpRedisServer::$frontendCloseCount++;
    echo HttpRedisServer::$frontendCloseCount . "\tfrontend[{$fd}] close\n";
    //清理掉后端连接
    if (isset(HttpRedisServer::$frontends[$fd]))
    {
        //$backend_socket = HttpRedisServer::$frontends[$fd];
        //$backend_socket->close();
        unset(HttpRedisServer::$frontends[$fd]);
    }
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    $fd = $req->fd;
    if (!isset(HttpRedisServer::$frontends[$fd]))
    {
        $redis = new swoole_redis;
        HttpRedisServer::$frontends[$req->fd] = $redis;
        $redis->on('close', function ($cli) use ($fd)
        {
            unset(HttpRedisServer::$frontends[$fd]);
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
        $redis = HttpRedisServer::$frontends[$req->fd];
        $redis->get("key", function($redis, $res) use ($resp){
            $resp->end("<h1>redis_result=".$res."</h1>");
        });
    }
});

HttpRedisServer::$serv = $serv;
$serv->start();
