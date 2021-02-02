<?php

namespace App\Loop;

use App\Helper\StringHelper;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\UnixServer;

trait SocketTrait
{
    /** @var ConnectionInterface $server */
    protected $server;
    
    /** @var ConnectionInterface $socket */
    private $socket;

    /** @var int $timeoutSocketWrite */
    private $timeoutSocketWrite = 30;

    /**
     * @param LoopInterface $loop
     *
     * @return UnixServer
     */
    private function initSocketServer(LoopInterface $loop)
    {
        if (file_exists(Constants::SOCKET_ADDRESS)) {
            unlink(Constants::SOCKET_ADDRESS);
        }
        
        $this->server = $server = new UnixServer(Constants::SOCKET_ADDRESS, $loop);

        $server->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                $this->handleDataServerSocket($connection, $data);
            });

            $connection->on('error', function (\Exception $e) {
                echo 'error: ' . $e->getMessage();
            });

            $connection->on('end', function () {
//                echo 'ended';
            });

            $connection->on('close', function () {
//                echo 'closed';
            });
        });

        $server->on('error', function (\Exception $e) {
            echo 'error: ' . $e->getMessage() . PHP_EOL;
        });
        
        $this->subscribeToClient();

        return $server;
    }

    /**
     * @param LoopInterface $loop
     */
    private function initSocketClient(LoopInterface $loop)
    {
        $connector = new Connector($loop);

        $connector->connect("unix://" . Constants::SOCKET_ADDRESS)->then(function (ConnectionInterface $connection) {
            $this->socket = $connection;
            $this->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_CONNECT));

            $connection->on('data', function ($data) {
                $this->handleDataClientSocket($data);
            });

            $connection->on('error', function (\Exception $e) {
                echo 'error: ' . $e->getMessage();
            });

            $connection->on('end', function () {
                echo 'ended';
            });
        });

        $this->on(Constants::SOCKET_CHANNEL_PING, function (SocketMessage $message) use ($loop) {
            $this->write(new SocketMessage(Constants::SOCKET_CHANNEL_PONG, [
                'status' => 'ok',
                'memory' => memory_get_peak_usage(true) / 1024 / 1024,
            ], $message->getXid()));
        });

        $this->on(Constants::SOCKET_CHANNEL_CLIENT_SETTINGS, function (SocketMessage $message) {
            if (!is_null($timeout = $message->getField('timeoutSocketWrite')) && $timeout > 0) {
                $this->timeoutSocketWrite = $timeout;
            }
        });
    }

    /**
     * Метод для записи клиентом о количестве задач на обработку.
     * 
     * @param int $count
     *
     * @return bool
     */
    private function setCountJobReadyClient($count)
    {
        return $this->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_JOB_READY, ['countJobReady' => (int) $count]));
    }

    /**
     * Метод для записи клиентом в сокет.
     *
     * @param SocketMessage $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    private function write(SocketMessage $message, callable $callback = null, callable $timeoutCallback = null)
    {
        $timer = null;

        if (!$this->socket) {
            return false;
        }

        // ставим таймер на ответ, если за это время не придет ответ, то вызовем колбэк
        $timer = $this->loop->addTimer($this->timeoutSocketWrite, function (TimerInterface $timer) use ($timeoutCallback, $message) {
            if (!is_null($timeoutCallback) && is_callable($timeoutCallback)) {
                $timeoutCallback();
            }

            if (!is_null($message->getXid())) {
                $this->removeAllListeners($message->getXid());
            }
        });

        // подписываемся на ответ запроса
        $this->on($message->getXid(), function (SocketMessage $response) use ($callback, $timer) {
            if (!is_null($callback) && is_callable($callback)) {
                $callback($response);
            }

            $this->loop->cancelTimer($timer);
            $this->removeAllListeners($response->getXid());
        });

        return $this->socket->write((string) $message);
    }

    /**
     * Обработчик сокета мастер процесса, сюда падает все что клиенты пишут.
     *
     * @param ConnectionInterface $connection
     * @param string $data
     */
    private function handleDataServerSocket(ConnectionInterface $connection, string $data)
    {
        $data = explode("\n", $data);

        foreach ($data as $item) {
            if (StringHelper::isJson($item)) {
                $item = json_decode($item, true);
            }

            if (!is_array($item)) {
               return;
            }

            $channel = $item['channel'] ?? null;
            $payload = $item['payload'] ?? [];
            $pid = $item['pid'] ?? null;
            $xid = $item['xid'] ?? null;
            /** @var CommandLoop $command */
            $command = $this->getCommandByPid($pid);

            if (!is_array($payload) || empty($channel) || is_null($command)) {
                return;
            }

            $message = new SocketMessage($channel, $payload, $xid, $pid);
            $message->setRawData(json_encode($item));

            if (!is_null($xid)) {
                $command->emit($xid, [$message]);
            }

            $command->setSocket($connection);
            $command->setCurrentPidWrite($pid);
            $command->setSocketPid($pid, $connection);

            // логируем что отправили по сокету клиенты
            if (Constants::SOCKET_CHANNEL_CLIENT_JOB_READY !== $channel ) {
                $command->emit(Constants::CHANNEL_CLIENT_WRITE, [$item]);
            }

            $this->emit($channel, [$message, $command]);
        }
    }

    /**
     * Обработчик клиентского сокета и выброс события по каналам данных
     *
     * @param string $data
     */
    private function handleDataClientSocket(string $data)
    {
        echo $data . PHP_EOL;

        $data = explode("\n", $data);

        foreach ($data as $item) {
            if (StringHelper::isJson($item)) {
                $item = json_decode($item, true);
            }

            if (!is_array($item)) {
                return;
            }

            $channel = $item['channel'];
            $payload = $item['payload'] ?? [];
            $xid = $item['xid'] ?? null;

            if (!is_array($payload) || empty($channel) ) {
                return;
            }

            $message = new SocketMessage($channel, $payload, $xid);
            $message->setRawData(json_encode($item));

            if (!is_null($xid)) {
                $this->emit($xid, [$message]);
            }

            $this->emit($channel, [$message]);
        }
    }

    /**
     * Подписка на сокет каналов клиента
     */
    private function subscribeToClient()
    {
        // канал что подключился новый клиент
        $this->on(Constants::SOCKET_CHANNEL_CLIENT_CONNECT, function (SocketMessage $message, CommandLoop $command) {
            $command->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_SETTINGS, [
                'interval' => $command->getIntervalTick(),
                'maxMemory' => $command->getUseMaxMemory(),
                'timeoutSocketWrite' => $command->getTimeoutSocketWrite(),
            ], $message->getXid()));
        });

        // канал для получения количества задач для обработки
        $this->on(Constants::SOCKET_CHANNEL_CLIENT_JOB_READY, function (SocketMessage $message, CommandLoop $command) {
            if (is_null($count = $message->getField('countJobReady'))) {
                return;
            }

            $command->setCountJobReady($count);
        });
    }
}
