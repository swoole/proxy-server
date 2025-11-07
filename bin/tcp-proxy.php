<?php

use Swoole\Async\Client;
use Swoole\Server;

class ProxyServer
{
    protected array $frontends;
    protected array $backends;

    protected $serv;
    protected int $index = 0;
    protected int $mode = SWOOLE_BASE;
    protected int $workerNum = 1;
    protected array $backendServer = array('host' => '127.0.0.1', 'port' => '80');

    public function run(): void
    {
        $serv = new Server("127.0.0.1", 9509, $this->mode);
        $serv->set(array(
            'worker_num' => $this->workerNum,
            //'log_file' => '/tmp/swoole.log', //swoole error log
        ));
        $serv->on('WorkerStart', array($this, 'onStart'));
        $serv->on('Receive', array($this, 'onReceive'));
        $serv->on('Close', array($this, 'onClose'));
        $serv->on('WorkerStop', array($this, 'onShutdown'));
        $serv->start();
    }

    public function onStart($serv): void
    {
        $this->serv = $serv;
        echo "Server: start.Swoole version is [" . SWOOLE_VERSION . "]\n";
    }

    public function onShutdown($serv): void
    {
        echo "Server: onShutdown\n";
    }

    public function onClose($serv, $fd, $reactor_id): void
    {
        if (isset($this->frontends[$fd])) {
            $backend_socket = $this->frontends[$fd];
            $backend_socket->closing = true;
            $backend_socket->close();
            unset($this->backends[$backend_socket->sock]);
            unset($this->frontends[$fd]);
        }
        echo "onClose: frontend[$fd]\n";
    }

    public function onReceive($serv, $fd, $reactor_id, $data)
    {
        //尚未建立连接
        if (!isset($this->frontends[$fd])) {
            //连接到后台服务器
            $socket = new Client(SWOOLE_SOCK_TCP);
            $socket->closing = false;
            $socket->on('connect', function (Client $socket) use ($data) {
                $socket->send($data);
            });

            $socket->on('error', function (Client $socket) use ($fd) {
                echo "ERROR: connect to backend server failed\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            });

            $socket->on('close', function (Client $socket) use ($fd) {
                echo "onClose: backend[{$socket->sock}]\n";
                unset($this->backends[$socket->sock]);
                unset($this->frontends[$fd]);
                if (!$socket->closing) {
                    $this->serv->close($fd);
                }
            });

            $socket->on('receive', function (Client $socket, $_data) use ($fd) {
                $this->serv->send($fd, $_data);
            });

            if ($socket->connect($this->backendServer['host'], $this->backendServer['port'])) {
                $this->backends[$socket->sock] = $fd;
                $this->frontends[$fd] = $socket;
            } else {
                echo "ERROR: cannot connect to backend server.\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            }
        } else {
            /**
             * @var $socket Client
             */
            $socket = $this->frontends[$fd];
            $socket->send($data);
        }
    }
}

$serv = new ProxyServer();
$serv->run();
