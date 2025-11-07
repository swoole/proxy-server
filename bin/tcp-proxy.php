<?php

use Swoole\Async\Client;
use Swoole\Server;
use Swoole\Timer;

class ProxyServer
{
    protected array $upstreams;
    protected array $rx_buffer;
    protected array $closing_fds;

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
        $serv->on('Connect', [$this, 'onConnect']);
        $serv->on('Receive', array($this, 'onReceive'));
        $serv->on('Close', array($this, 'onClose'));
        $serv->on('WorkerStop', array($this, 'onShutdown'));
        $serv->start();
    }

    public function onStart($serv): void
    {
        $this->serv = $serv;
        echo "Server is started, SWOOLE version is [" . SWOOLE_VERSION . "]\n";
    }

    public function onShutdown($serv): void
    {
        echo "Server: onShutdown\n";
    }

    public function onConnect($serv, $fd): void {
        $this->rx_buffer[$fd] = [];
    }

    public function onClose($serv, $fd, $reactor_id): void
    {
        if (isset($this->upstreams[$fd])) {
            $socket = $this->upstreams[$fd];
            $this->closing_fds[$socket->sock] = true;
            $socket->close();
            unset($this->upstreams[$fd]);
        }
        unset($this->rx_buffer[$fd]);
    }

    protected function getSocket($fd): Client
    {
        return $this->upstreams[$fd];
    }

    public function onReceive($serv, $fd, $reactor_id, $data): void
    {
        if (!isset($this->upstreams[$fd])) {
            $socket = new Client(SWOOLE_SOCK_TCP);
            $this->upstreams[$fd] = $socket;
            $this->rx_buffer[$fd][] = $data;
            $socket->on('connect', function (Client $socket) use ($fd) {
                foreach ($this->rx_buffer[$fd] as $data) {
                    $socket->send($data);
                }
                $this->closing_fds[$socket->sock] = false;
            });

            $socket->on('error', function (Client $socket) use ($fd) {
                echo "ERROR: connect to backend server failed\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            });

            $socket->on('close', function (Client $socket) use ($fd) {
                if (!$this->closing_fds[$socket->sock]) {
                    $this->serv->close($fd);
                }
                unset($this->upstreams[$fd]);
                unset($this->rx_buffer[$fd]);
                unset($this->closing_fds[$socket->sock]);
            });

            $socket->on('receive', function (Client $socket, $_data) use ($fd) {
                $this->serv->send($fd, $_data);
            });

            if (!$socket->connect($this->backendServer['host'], $this->backendServer['port'])) {
                echo "ERROR: cannot connect to backend server.\n";
                $this->serv->send($fd, "backend server not connected. please try reconnect.");
                $this->serv->close($fd);
            }
        } else {
            $socket = $this->getSocket($fd);
            if ($socket->isConnected()) {
                $socket->send($data);
            } else {
                $this->rx_buffer[$fd][] = $data;
            }
        }
    }
}

$serv = new ProxyServer();
$serv->run();
