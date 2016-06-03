<?php
class HttpMySQLServer
{
    static $frontendCloseCount = 0;
    static $backends = array();
    static $serv;
}

$serv = new swoole_http_server('127.0.0.1', 9512, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
//$serv->set(array('worker_num' => 8));

$serv->on('Close', function ($serv, $fd, $reactorId)
{
    echo "client[{$fd}] is closed\n";
    //清理掉后端连接
    if (isset(HttpMySQLServer::$backends[$fd]))
    {
        $backend_socket = HttpMySQLServer::$backends[$fd];
        $backend_socket->close();
        unset(HttpMySQLServer::$backends[$fd]);
    }
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    $fd = $req->fd;
    if (!isset(HttpMySQLServer::$backends[$fd]))
    {
        $db = new swoole_mysql;
        HttpMySQLServer::$backends[$fd] = $db;
        $db->on('close', function ($cli) use ($fd)
        {
            unset(HttpMySQLServer::$backends[$fd]);
            echo "mysql-client#{$fd}] is closed\n";
        });
        $r = $db->connect(array(
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'root',
            'password' => 'root',
            'database' => 'test',
        ));
        if ($r === false)
        {
            var_dump($db->error, $db->errno);
            die();
        }
    }
    else
    {
        $db =  HttpMySQLServer::$backends[$fd];
    }
    $db->query("show tables", function($db, $res) use ($resp){
        $resp->end("<pre>mysql_result=".var_export($res, true)."</pre>");
    });
});

HttpMySQLServer::$serv = $serv;
$serv->start();
