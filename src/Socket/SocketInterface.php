<?php

namespace App\Socket;

use App\Loop\SocketMessage;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

interface SocketInterface
{
    /**
     * @param string|null $uri
     *
     * @return $this
     */
    public function connect(string $uri = null): self;

    /**
     * @return void
     */
    public function close();

    /**
     * @param SocketMessage $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function write(SocketMessage $message, callable $callback = null, callable $timeoutCallback = null): bool;

    /**
     * @param ConnectionInterface $connection
     * @param SocketMessage $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function writeByConnection(ConnectionInterface $connection, SocketMessage $message, callable $callback = null, callable $timeoutCallback = null): bool;

    /**
     * @param ConnectionInterface $conn
     *
     * @return $this
     */
    public function setConnection(ConnectionInterface $conn);

    /**
     * @param string $uri
     *
     * @return $this
     */
    public function setUri(string $uri);

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setWaitForAnAnswer(bool $value);

    /**
     * @param LoopInterface $loop
     *
     * @return $this
     */
    public function setLoop(LoopInterface $loop);

    /**
     * @param int $timeoutSocketWrite
     *
     * @return $this
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite);
}
