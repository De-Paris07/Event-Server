<?php

namespace App\Socket;

use App\Helper\StringHelper;
use App\Loop\SocketMessage;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;

/**
 * Class AbstractSocket
 *
 * @package Encry\ClientEventBundle\Socket
 */
abstract class AbstractSocket implements SocketInterface
{
    use EventEmitterTrait;

    const SOCKET_CONNECT_CHANNEL = 'socket.connect';
    const SOCKET_DISCONNECT_CHANNEL = 'socket.disconnect';
    const SOCKET_CLOSE_CHANNEL = 'socket.close';
    const SOCKET_CONNECT_CLIENT_CHANNEL = 'socket.connect.client';
    const SOCKET_DISCONNECT_CLIENT_CHANNEL = 'socket.disconnect.client';
    const SOCKET_CLOSE_SERVER_CHANNEL = 'socket.close.server';
    const SOCKET_ERROR_CHANNEL = 'socket.error';
    const SOCKET_TIMEOUT_CONNECT_CHANNEL = 'socket.timeout.connect';
    const SOCKET_CONNECTED_ERROR_CHANNEL = 'socket.connected.error';
    const SOCKET_RAW_MESSAGE = 'socket.raw.message';
    
    const SOCKET_LOAD_ROUTES_CHANNEL = 'socket.load.routes';
    const SOCKET_HEALTH_CHECK = 'health.check';
    const SOCKET_CHANNEL_HEALTH_CHECK_DATA = 'health.check.data';
    const SOCKET_SERVICES_LIST = 'services.list';
    const SOCKET_QUERY_DISPATCH = 'query.dispatch';

    /** @var LoopInterface $loop */
    protected $loop;

    /** @var ConnectionInterface $connection */
    protected $connection;

    /** @var string $uri */
    protected $uri;

    /** @var bool $waitForAnAnswer */
    protected $waitForAnAnswer = true;

    /** @var bool $running */
    protected $running = false;

    /** @var int $timeoutSocketWrite */
    protected $timeoutSocketWrite = 30;

    /** @var string $messageDelimiter */
    protected $messageDelimiter = "\n";

    /** @var callable $parseMessageFun */
    protected $parseMessageFun;

    /** @var string $messageObject */
    protected $messageObject = SocketMessage::class;

    /** @var array<string> | null $buffer */
    private $buffer;

    /** @var bool $asyncWrite */
    private $asyncWrite = false;

    /** @var bool $debug */
    private $debug = true;

    public abstract function close();

    /**
     * @param string | null $uri
     *
     * @return $this
     */
    public abstract function connect(string $uri = null): SocketInterface;

    /**
     * @return ConnectionInterface | null
     */
    protected abstract function getConnection(): ?ConnectionInterface;

    /**
     * AbstractSocket constructor.
     *
     * @param null $uri
     */
    public function __construct($uri = null)
    {
        $this->uri = $uri;
    }

    /**
     * @param SocketMessage $message
     * @param callable|null $callback
     * @param callable|null $timeoutCallback
     *
     * @return bool
     */
    public function write(SocketMessage $message, callable $callback = null, callable $timeoutCallback = null): bool
    {
        $timer = null;

        if (is_null($this->getConnection())) {
            return false;
        }

        if (!$this->loop instanceof LoopInterface) {
            throw new \RuntimeException('To write to the socket, you must pass the "' . LoopInterface::class .  '" interface object');
        }

        if ($this->waitForAnAnswer) {
            // ставим таймер на ответ, если за это время не придет ответ, то вызовем колбэк
            $timer = $this->loop->addTimer($this->timeoutSocketWrite, function (TimerInterface $timer) use ($timeoutCallback, $message) {
                $this->removeAllListeners($message->getXid());

                if (!is_null($timeoutCallback) && is_callable($timeoutCallback)) {
                    $timeoutCallback();
                }
            });

            // подписываемся на ответ запроса
            $this->on($message->getXid(), function (SocketMessageInterface $response) use ($callback, $timer, $message) {
                if (!is_null($timer) && !is_null($this->loop)) {
                    $this->loop->cancelTimer($timer);
                }

                $this->removeAllListeners($message->getXid());

                if (!is_null($callback) && is_callable($callback)) {
                    $callback($response);
                }
            });
        }

        return $this->connection
            ->setAsyncWrite($this->isAsyncWrite())
            ->write((string) $message);
    }

    /**
     * @param ConnectionInterface $connection
     * @param SocketMessage $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function writeByConnection(
        ConnectionInterface $connection,
        SocketMessage $message,
        callable $callback = null,
        callable $timeoutCallback = null
    ): bool {
        return $this
            ->setConnection($connection)
            ->write($message, $callback, $timeoutCallback);
    }

    public function start()
    {
        if (is_null($this->loop)) {
            return;
        }

        $this->loop->run();
    }

    public function stop()
    {
        $this->loopStop();
    }

    /**
     * @param ConnectionInterface $conn
     *
     * @return $this
     */
    public function setConnection(ConnectionInterface $conn) 
    {
        $this->connection = $conn;
        
        return $this;
    }

    /**
     * @return LoopInterface | null
     */
    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    /**
     * @param LoopInterface $loop
     *
     * @return $this
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     *
     * @return $this
     */
    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeoutSocketWrite(): int
    {
        return $this->timeoutSocketWrite;
    }

    /**
     * @param int $timeoutSocketWrite
     *
     * @return $this
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): self
    {
        $this->timeoutSocketWrite = $timeoutSocketWrite;

        return $this;
    }

    /**
     * @return bool
     */
    public function isWaitForAnAnswer(): bool
    {
        return $this->waitForAnAnswer;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setWaitForAnAnswer(bool $value)
    {
        $this->waitForAnAnswer = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessageDelimiter(): string
    {
        return $this->messageDelimiter;
    }

    /**
     * @param string $messageDelimiter
     *
     * @return $this
     */
    public function setMessageDelimiter(string $messageDelimiter): self
    {
        $this->messageDelimiter = $messageDelimiter;

        return $this;
    }

    /**
     * @return callable
     */
    public function getParseMessageFun(): callable
    {
        return $this->parseMessageFun;
    }

    /**
     * @param callable $parseMessageFun
     *
     * @return $this
     */
    public function setParseMessageFun(callable $parseMessageFun): self
    {
        $this->parseMessageFun = $parseMessageFun;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessageObject(): string
    {
        return $this->messageObject;
    }

    /**
     * @param string $messageObject
     *
     * @return $this
     */
    public function setMessageObject(string $messageObject): self
    {
        if (!is_a($messageObject, SocketMessageInterface::class, true)) {
            throw new \RuntimeException(sprintf('The "%s" object of the message must implement the "%s" interface', $messageObject, SocketMessageInterface::class));
        }

        $this->messageObject = $messageObject;

        return $this;
    }

    /**
     * @return array | null
     */
    public function getBuffer(): ?array
    {
        return $this->buffer;
    }

    /**
     * @param string $buffer
     *
     * @return $this
     */
    public function addBuffer(string $buffer): self
    {
        $this->buffer[] = $buffer;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAsyncWrite(): bool
    {
        return $this->asyncWrite;
    }

    /**
     * @param bool $asyncWrite
     *
     * @return SocketInterface
     */
    public function setAsyncWrite(bool $asyncWrite): SocketInterface
    {
        $this->asyncWrite = $asyncWrite;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     *
     * @return SocketInterface
     */
    public function setDebug(bool $debug): SocketInterface
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param ConnectionInterface $connection
     * @param string $data
     */
    protected function handleDataSocket(ConnectionInterface $connection, string $data)
    {
        $this->running = true;

        // если пришло одно сообщение и оно является не полным, кладем в буфер и ожидаем следующих частей
        if (!mb_stripos($data, $this->getMessageDelimiter()) && count($result = explode($this->getMessageDelimiter(), $data)) === 1) {
            $this->buffer[] = $data;
            $this->running = false;

            if ($this->isDebug()) {
                echo 'Положили в буфер часть сообщения: ' . $data . PHP_EOL;
            }

            return;
        }

        $data = explode($this->getMessageDelimiter(), $data);

        foreach ($data as $key => $rawMessage) {
            $item = $this->parseMessage($rawMessage);

            if (!is_array($item)) {
                continue;
            }

            $channel = $item['channel'] ?? null;
            $payload = $item['payload'] ?? [];
            $pid = $item['pid'] ?? null;
            $xid = $item['xid'] ?? null;

            if (!is_array($payload) || empty($channel)) {
                continue;
            }

            $this->setConnection($connection);
            /** @var SocketMessageInterface $message */
            $message = new $this->messageObject($channel, $payload, $xid, $pid);
            $message->setConnection($connection);
            $message->setRawData($rawMessage);

            if (!is_null($xid)) {
                $this->emit($xid, [$message]);
            }

            $this->emit($channel, [$message]);
            $this->emit(self::SOCKET_RAW_MESSAGE, [$item]);
        }

        $this->running = false;
    }

    /**
     * @param string $message
     *
     * @return array | null
     */
    protected function parseMessage(string $message): ?array
    {
        if ('' === $message) {
            return null;
        }

        if (!is_null($fun = $this->parseMessageFun)) {
            return $fun($message);
        }

        if (StringHelper::isJson($message)) {
            return json_decode($message, true);
        }

        if (is_null($this->buffer)) {
            $this->buffer[] = $message;

            if ($this->isDebug()) {
                echo 'Положили в буфер часть сообщения: ' . $message . PHP_EOL;
            }

            return null;
        }

        $this->buffer[] = $message;
        $message = implode('', $this->buffer);

        if (StringHelper::isJson($message)) {
            $this->buffer = null;

            return json_decode($message, true);
        }

        if ($this->isDebug()) {
            echo 'Положили в буфер часть сообщения: ' . $message . PHP_EOL;
        }

        return null;
    }
    
    protected function loopStop()
    {
        if (is_null($this->loop)) {
            return;
        }

        $this->loop->stop();
        $this->loop = null;
    }
}
