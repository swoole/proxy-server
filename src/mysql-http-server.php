<?php

class HttpMySQLServer
{
    public static $frontendCloseCount = 0;
    public static $backends = array();
    public static $serv;
    public static $mysqlServerConfig = array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => 'root',
        'database' => 'test',
    );
}

$serv = new swoole_http_server('127.0.0.1', 9512, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
//$serv->set(array('worker_num' => 8));

$serv->on('Close', function ($serv, $fd, $reactorId) {
    echo "client[{$fd}] is closed\n";
    //清理掉后端连接
    if (isset(HttpMySQLServer::$backends[$fd])) {
        //debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $db = HttpMySQLServer::$backends[$fd];
        $db->close();
        unset(HttpMySQLServer::$backends[$fd]);
    }
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp) {
    $fd = $req->fd;
    if (!isset(HttpMySQLServer::$backends[$fd])) {
        $db = new swoole_mysql();
        $db->on('close', function ($db) use ($fd) {
            echo "mysql-client[{$fd}#{$db->sock}] is closed\n";
        });
        $db->connect(HttpMySQLServer::$mysqlServerConfig, function ($db, $result) use ($fd, $resp) {
            HttpMySQLServer::$backends[$fd] = $db;
            if ($result === false) {
                var_dump($db->error, $db->errno);
                die();
            }
            $db->query("show tables", function ($db, $res) use ($resp) {
                $resp->end("<pre>mysql_result=" . var_export($res, true) . "</pre>");
            });
        });
    } else {
        $db = HttpMySQLServer::$backends[$fd];
        $db->query("show tables", function ($db, $res) use ($resp) {
            $resp->end("<pre>mysql_result=" . var_export($res, true) . "</pre>");
        });
    }
});

HttpMySQLServer::$serv = $serv;
$serv->start();
