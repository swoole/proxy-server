<?php
class ProxyServer
{
    protected $frontends;
    protected $backends;
    /**
     * @var swoole_server
     */
    protected $serv;
    protected $index = 0;
    protected $mode = SWOOLE_PROCESS;
    protected $backendServer = array('host' => '127.0.0.1', 'port' => '80');

    function run()
    {
        $serv = new swoole_server("127.0.0.1", 9509, $this->mode);
        $serv->set(array(
            //'worker_num' => 8, //worker process num
            'backlog' => 128, //listen backlog
            //'open_tcp_keepalive' => 1,
            //'log_file' => '/tmp/swoole.log', //swoole error log
        ));
        $serv->on('WorkerStart', array($this, 'onStart'));
        $serv->on('Receive', array($this, 'onReceive'));
        $serv->on('Close', array($this, 'onClose'));
        $serv->on('WorkerStop', array($this, 'onShutdown'));
        $serv->start();
    }

    function onStart($serv)
    {
        $this->serv = $serv;
        echo "Server: start.Swoole version is [" . SWOOLE_VERSION . "]\n";
    }

    function onShutdown($serv)
    {
        echo "Server: onShutdown\n";
    }

    function onClose($serv, $fd, $from_id)
    {
        //清理掉后端连接
        if (isset($this->frontends[$fd]))
        {
            $backend_socket = $this->frontends[$fd];
            $backend_socket->close();
            unset($this->backends[$backend_socket->sock]);
            unset($this->frontends[$fd]);
        }
        echo "onClose: frontend[$fd]\n";
    }

    function onReceive($serv, $fd, $from_id, $data)
    {
        //尚未建立连接
        if (!isset($this->frontends[$fd]))
        {
            //连接到后台服务器
            $socket = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

            $socket->on('connect', function (swoole_client $socket) use ($data)
            {
                $socket->send($data);
            });

            $socket->on('error', function (swoole_client $socket) use ($fd)
            {
                echo "ERROR: connect to backend server failed\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            });

            $socket->on('close', function (swoole_client $socket) use ($fd)
            {
                echo "onClose: backend[{$socket->sock}]\n";
                unset($this->backends[$socket->sock]);
                unset($this->frontends[$fd]);
                $this->serv->close($fd);
            });

            $socket->on('receive', function (swoole_client $socket, $_data) use ($fd)
            {
                //PHP-5.4以下版本可能不支持此写法，匿名函数不能调用$this
                //可以修改为类静态变量
                $this->serv->send($fd, $_data);
            });

            if ($socket->connect($this->backendServer['host'], $this->backendServer['port']))
            {
                $this->backends[$socket->sock] = $fd;
                $this->frontends[$fd] = $socket;
            }
            else
            {
                echo "ERROR: cannot connect to backend server.\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            }
        }
        //已经有连接，可以直接发送数据
        else
        {
            /**
             * @var $socket swoole_client
             */
            $socket = $this->frontends[$fd];
            $socket->send($data);
        }
    }
}

$serv = new ProxyServer();
$serv->run();
