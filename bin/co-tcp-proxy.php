<?php

use Swoole\Server;
use Swoole\Coroutine\Client;
use Swoole\Coroutine\Channel;

class CoProxyServer
{
    protected array $channels = [];
    protected array $sockets = [];
    protected Server $server;
    protected int $index = 0;
    protected int $workerNum = 1;
    protected array $backendServer = array('host' => '127.0.0.1', 'port' => '80');

    public function run(): void
    {
        $serv = new Server("127.0.0.1", 9508);
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
        $this->server = $serv;
        echo "Server is started, SWOOLE version is [" . SWOOLE_VERSION . "]\n";
    }

    public function onShutdown($serv): void
    {
        echo "Server: onShutdown\n";
    }

    public function onConnect($serv, $fd): void
    {
        $channel = new Channel(64);
        $this->channels[$fd] = $channel;

        $socket = new Client(SWOOLE_SOCK_TCP);
        if (!$socket->connect($this->backendServer['host'], $this->backendServer['port'])) {
            trigger_error("bad backend server " . $this->backendServer['host'] . ":" . $this->backendServer['port'] . ', Error: ' . $socket->errCode, E_USER_WARNING);
            $serv->close($fd);
            return;
        }

        // read from backend and send to client
        Co\go(function () use ($serv, $socket, $fd) {
            while (true) {
                $data = $socket->recv();
                if ($data === '' || $data === false) {
                    break;
                }
                $serv->send($fd, $data);
            }
            if ($serv->exist($fd)) {
                $serv->close($fd);
            }
        });

        // read from client and send to backend
        Co\go(function () use ($socket, $channel) {
            while (true) {
                $data = $channel->pop();
                if ($data === '' || $data === false) {
                    break;
                }
                $socket->send($data);
            }
        });

        $this->sockets[$fd] = $socket;
    }

    public function onClose($serv, $fd, $reactor_id): void
    {
        if (isset($this->channels[$fd])) {
            $channel = $this->channels[$fd];
            $channel->close();
        }
        if (isset($this->sockets[$fd])) {
            $socket = $this->sockets[$fd];
            $socket->close();
        }
    }

    public function onReceive($serv, $fd, $reactor_id, $data): void
    {
        $channel = $this->channels[$fd];
        $channel->push($data);
    }
}

$serv = new CoProxyServer();
$serv->run();
