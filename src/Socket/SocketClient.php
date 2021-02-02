<?php

namespace App\Socket;

use App\Exception\ConnectTimeoutException;
use App\Loop\LoopFactory;
use App\Loop\SocketMessage;
use App\Socket\Client\Connector;
use React\EventLoop\Factory;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;

/**
 * Class SocketClient
 *
 * @package Encry\ClientEventBundle\Socket
 */
class SocketClient extends AbstractSocket
{
    /** @var int $timeoutConnect */
    private $timeoutConnect = 30;

    /** @var TimerInterface $reconnectTimer */
    private $reconnectTimer;
    
    /** @var bool $isClose */
    private $isClose = false;
    
    /** @var bool $isReconnect */
    private $isReconnect = true;

    /**
     * @param string|null $uri
     * @return SocketInterface
     * @throws \Exception
     */
    public function connect(string $uri = null): SocketInterface
    {
        $this->isClose = false;
        
        if (!is_null($this->connection)) {
            return $this;
        }
        
        if (is_null($this->loop)) {
            $this->loop = LoopFactory::getLoop();
        }

        if (is_null($this->uri) && !is_null($uri)) {
            $this->uri = $uri;
        }
        
        if (is_null($this->uri)) {
            throw new \Exception('Not found uri socket.');
        }

        $connector = new Connector($this->loop);
        $connector->connect($this->uri)->then(function (ConnectionInterface $connection) {
            if (!is_null($this->reconnectTimer)) {
                $this->loop->cancelTimer($this->reconnectTimer);
            }

            $this->isClose = false;
            $this->reconnectTimer = null;
            $this->connection = $connection;

            $connection->on('data', function ($data) use ($connection) {
                $this->handleDataSocket($connection, $data);
            });

            $connection->on('error', function (\Exception $e) {
                if ($this->isDebug()) {
                    echo 'error: ' . $e->getMessage() . PHP_EOL;
                }
                
                $this->emit(self::SOCKET_ERROR_CHANNEL, [$e->getMessage()]);
            });

            $connection->on('close', function () use ($connection) {
                if ($this->isDebug()) {
                    echo 'Ended node socket' . PHP_EOL;    
                }
                
                $this->connection = null;
                $this->emit(self::SOCKET_DISCONNECT_CHANNEL, [$connection]);
            });
            
            $this->emit(self::SOCKET_CONNECT_CHANNEL);
        }, function ($exception) {
            if (!$this->isReconnect) {
                $this->emit(self::SOCKET_CONNECTED_ERROR_CHANNEL, [$exception->getMessage()]);
            }
        });

        if (!$this->reconnectTimer && $this->isReconnect) {
            $this->reconnectSocket();
        }

        if ($this->isReconnect && $this->timeoutConnect > 0 && !is_null($this->loop)) {
            $this->loop->addTimer($this->timeoutConnect, function ($timer) {
                if ($this->isClose) {
                    return;
                }

                if (is_null($this->connection)) {
                    $exception = new ConnectTimeoutException($this->timeoutConnect, 'Connection to ' . $this->uri . ' timed out after ' . $this->timeoutConnect . ' seconds');
                    $this->emit(self::SOCKET_TIMEOUT_CONNECT_CHANNEL, [$exception->getMessage()]);
                    throw $exception;
                }
            });
        }

        return $this;
    }

    /**
     * @param SocketMessage $message
     * @param callable|null $callback
     * @param callable|null $timeoutCallback
     * @param bool $isAsync
     * 
     * @throws \Exception
     */
    public function writeWithoutListening(
        SocketMessage $message,
        callable $callback = null,
        callable $timeoutCallback = null,
        bool $isAsync = false
    ) {
        if (is_null($this->uri)) {
            throw new \Exception('Not found uri socket.');
        }

        $this->setLoop(!$isAsync ? Factory::create() : LoopFactory::getLoop());
        $this->on(self::SOCKET_CONNECT_CHANNEL, function () use ($message, $callback, $timeoutCallback, $isAsync) {
            $async = $this->isAsyncWrite();
            $this
                ->setAsyncWrite(false)
                ->write($message,
                    function (SocketMessage $message) use ($callback, $async, $isAsync) {
                        $this->setAsyncWrite($async);
                        $this->close();

                        if (!$isAsync) {
                            $this->loopStop();
                        }

                        $this->removeAllListeners(self::SOCKET_CONNECT_CHANNEL);

                        if (!is_null($callback) && is_callable($callback)) {
                            $callback($message);
                        }
                    }, function () use ($timeoutCallback, $async, $isAsync) {
                        $this->setAsyncWrite($async);
                        $this->close();

                        if (!$isAsync) {
                            $this->loopStop();
                        }

                        $this->removeAllListeners(self::SOCKET_CONNECT_CHANNEL);

                        if (!is_null($timeoutCallback) && is_callable($timeoutCallback)) {
                            $timeoutCallback();
                        }
                    });

            // если не ожидаем ответа на наше сообщение, завершаем цикл, т.к запись в синхронном режиме
            if (!$this->waitForAnAnswer) {
                $this->setAsyncWrite($async);
                $this->close();

                if (!$isAsync) {
                    $this->loopStop();
                }

                $this->removeAllListeners(self::SOCKET_CONNECT_CHANNEL);
            }
        });

        $this->connect();

        if (!$isAsync) {
            $this->start();
            $this->setLoop(LoopFactory::getLoop());
        }
    }

    /**
     * @param callable | null $callback
     */
    public function handleByffer(callable $callback = null)
    {
        while (true) {
            if ('' != $data = \stream_get_contents($this->connection->stream, -1)) {
                $this->handleDataSocket($this->connection, $data);
                continue;
            }   
            
            break;
        }

        if (!is_null($callback)) {
            $callback();
        }
    }

    public function close()
    {
        if (!is_null($this->reconnectTimer) && !is_null($this->loop)) {
            $this->loop->cancelTimer($this->reconnectTimer);
        }
        
        if (!is_null($this->connection)) {
            $this->connection->close();
            $this->connection = null;
        }
        
        $this->isClose = true;
    }
    
    /**
     * @return int
     */
    public function getTimeoutConnect()
    {
        return $this->timeoutConnect;
    }

    /**
     * @param int $timeoutConnect
     *
     * @return $this
     */
    public function setTimeoutConnect($timeoutConnect): self
    {
        $this->timeoutConnect = $timeoutConnect;

        return $this;
    }

    /**
     * @return bool
     */
    public function isReconnect(): bool
    {
        return $this->isReconnect;
    }

    /**
     * @param bool $isReconnect
     *
     * @return $this
     */
    public function setIsReconnect(bool $isReconnect): self
    {
        $this->isReconnect = $isReconnect;
        
        return $this;
    }

    /**
     * @return bool
     */
    public function isConnect()
    {
        return !is_null($this->connection);
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        return $this->running;
    }

    /**
     * @return ConnectionInterface | null
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    protected function reconnectSocket()
    {
        if (is_null($this->loop)) {
            return;
        }
        
        $this->loop->addPeriodicTimer(1, function ($timer) {
            $this->reconnectTimer = $timer;
            
            if ($this->isClose) {
                $this->loop->cancelTimer($timer);
                return;
            }

            if (!is_null($this->connection)) {
                return;
            }
            
            if ($this->isDebug()) {
                echo 'Reconnect socket - "' . $this->uri . '"' . PHP_EOL;   
            }

            $this->connect($this->uri);
        });
    }
}
