<?php

namespace App\Loop;

use Cron\CronExpression;
use Evenement\EventEmitter;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;

class CommandLoop extends EventEmitter
{
    /** @var string $name */
    private $name;

    /** @var string | null $command */
    private $command;

    /** @var bool $consumer */
    private $consumer;

    /** @var bool $daemon */
    private $daemon;

    /** @var integer $minInstance */
    private $minInstance;

    /** @var integer $maxInstance */
    private $maxInstance;

    /** @var integer | null $countJob */
    private $countJob;

    /** @var integer $timeoutCreate */
    private $timeoutCreate;

    /** @var float $intervalTick */
    private $intervalTick;

    /** @var Process[] $procesess */
    private $processes = [];

    /** @var LoopInterface $loop */
    private $loop;

    /** @var int $counJobReady */
    private $counJobReady = 0;

    /** @var int $useMaxMemory */
    private $useMaxMemory = 0;

    /** @var TimerInterface $timers */
    private $timerNewInstance;

    /** @var TimerInterface $timers */
    private $timerCloseInstance;

    /** @var ConnectionInterface $socket */
    private $socket;

    /** @var int $currentPidWrite */
    private $currentPidWrite;

    /** @var ConnectionInterface[] $socketPid */
    private $socketPid = [];

    /** @var int $timeoutSocketWrite */
    private $timeoutSocketWrite = 60;

    /** @var int $pingPollingInterval */
    private $pingPollingInterval = 300;

    /** @var string | null $schedule */
    private $schedule;

    /** @var int $startSecond */
    private $startSecond = 0;

    public function start()
    {
        $timer = null;

        if ($this->getCountProcesses() === $this->getMinInstance()) {
            return;
        }

        if ($this->getMinInstance() < 1) {
            return;
        }

        if (!is_null($this->getSchedule()) && !$this->isConsumer()) {
            $this->startScheduleDaemon();

            return;
        }

        if (!$this->isDaemon()) {
            $this->loop->addPeriodicTimer($this->getIntervalTick(), function (TimerInterface $timer) {
                if (0 !== $this->getCountProcesses()) {
                    return;
                }

                $this->startProcess();
            });
        }

        $this->startProcess($this->getMinInstance());

        if ($this->isDaemon()) {
            $this->loop->addPeriodicTimer(1, function (TimerInterface $timer) {
                if ($this->getCountProcesses() < $this->getMinInstance()) {
                    $this->startProcess($this->getMinInstance() - $this->getCountProcesses());
                }
            });
        }

        $this->loop->addPeriodicTimer($this->pingPollingInterval, function (TimerInterface $timer) {
            foreach ($this->socketPid as $pid => $socket) {
                $this->socket = $socket;
                $this->write(new SocketMessage(Constants::SOCKET_CHANNEL_PING, []), null, function () use ($pid) {
                    if (!is_null($process = $this->getProcess($pid))) {
                        $this->restartProcess($process);
                    }
                });
            }
        });
    }

    public function stop()
    {
        foreach ($this->processes as $process) {
            $this->removeProcess($process);
        }
    }

    /**
     * @param $pid
     *
     * @return Process | null
     */
    public function getProcess(?int $pid): ?Process
    {
        if (!isset($this->processes[$pid]) || is_null($pid)) {
            return null;
        }

        return $this->processes[$pid];
    }

    /**
     * @param Process $process
     */
    public function addProcess(Process $process)
    {
        $this->processes[$process->getPid()] = $process;
    }

    /**
     * @return int
     */
    public function getCountProcesses()
    {
        return count($this->processes);
    }

    /**
     * @return bool
     */
    public function isMaximumProcesses()
    {
        return  $this->getCountProcesses() >= $this->getMaxInstance();
    }

    /**
     * @param int $countJobReady
     */
    public function setCountJobReady(int $countJobReady): void
    {
        $this->counJobReady = $countJobReady;

        if ($this->getCountJobReady() > $this->getCountJob() && !$this->isMaximumProcesses() && is_null($this->timerNewInstance)) {
            $this->timerNewInstance = $this->loop->addPeriodicTimer($this->getTimeoutCreate(), function ($timer) {
                if ($this->getCountJobReady() > $this->getCountJob() && !$this->isMaximumProcesses()) {
                    $this->startProcess();

                    return;
                }

                $this->loop->cancelTimer($timer);
                $this->timerNewInstance = null;
            });
        }

        if ($this->getCountJobReady() < $this->getCountJob() && is_null($this->timerCloseInstance)) {
            $this->timerCloseInstance = $this->loop->addPeriodicTimer($this->getTimeoutCreate(), function ($timer) use (&$counJobReady) {
                if ($this->getCountProcesses() > $this->getMinInstance() && $this->getCountJobReady() < $this->getCountJob()) {
                    $this->removeProcess(current($this->processes));

                    return;
                }

                $this->loop->cancelTimer($timer);
                $this->timerCloseInstance = null;
            });
        }
    }

    /**
     * @param SocketMessage $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function write(SocketMessage $message, callable $callback = null, callable $timeoutCallback = null)
    {
        $timer = null;

        if (is_null($this->socket)) {
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getCommand(): ?string
    {
        return $this->command;
    }

    /**
     * @param string|null $command
     */
    public function setCommand(?string $command): void
    {
        $this->command = $command;
    }

    /**
     * @return bool
     */
    public function isConsumer(): bool
    {
        return $this->consumer;
    }

    /**
     * @param bool $consumer
     */
    public function setConsumer(bool $consumer): void
    {
        $this->consumer = $consumer;
    }

    /**
     * @return int
     */
    public function getMinInstance(): int
    {
        return $this->minInstance;
    }

    /**
     * @param int $minInstance
     */
    public function setMinInstance(int $minInstance): void
    {
        $this->minInstance = $minInstance;
    }

    /**
     * @return int
     */
    public function getMaxInstance(): int
    {
        return $this->maxInstance;
    }

    /**
     * @param int $maxInstance
     */
    public function setMaxInstance(int $maxInstance): void
    {
        $this->maxInstance = $maxInstance;
    }

    /**
     * @return int|null
     */
    public function getCountJob(): ?int
    {
        return $this->countJob;
    }

    /**
     * @param int|null $countJob
     */
    public function setCountJob(?int $countJob): void
    {
        $this->countJob = $countJob;
    }

    /**
     * @return int
     */
    public function getTimeoutCreate(): int
    {
        return $this->timeoutCreate;
    }

    /**
     * @param int $timeoutCreate
     */
    public function setTimeoutCreate(int $timeoutCreate): void
    {
        $this->timeoutCreate = $timeoutCreate;
    }

    /**
     * @return float
     */
    public function getIntervalTick(): float
    {
        return $this->intervalTick;
    }

    /**
     * @param float $intervalTick
     */
    public function setIntervalTick(float $intervalTick): void
    {
        $this->intervalTick = $intervalTick;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * @param LoopInterface $lopp
     */
    public function setLoop(LoopInterface $lopp): void
    {
        $this->loop = $lopp;
    }

    /**
     * @return int
     */
    public function getCountJobReady(): int
    {
        return $this->counJobReady;
    }

    /**
     * @param ConnectionInterface $socket
     */
    public function setSocket(ConnectionInterface $socket): void
    {
        $this->socket = $socket;
    }

    /**
     * @return int
     */
    public function getUseMaxMemory(): int
    {
        return $this->useMaxMemory;
    }

    /**
     * @param int $useMaxMemory
     */
    public function setUseMaxMemory(int $useMaxMemory): void
    {
        $this->useMaxMemory = $useMaxMemory;
    }

    /**
     * @return int
     */
    public function getCurrentPidWrite(): int
    {
        return $this->currentPidWrite;
    }

    /**
     * @param int $currentPidWrite
     */
    public function setCurrentPidWrite(int $currentPidWrite): void
    {
        $this->currentPidWrite = $currentPidWrite;
    }

    /**
     * @param int $pid
     * @param ConnectionInterface $socket
     */
    public function setSocketPid(int $pid, ConnectionInterface $socket)
    {
        if (array_key_exists($pid, $this->socketPid)) {
            return;
        }

        $this->socketPid[$pid] = $socket;
    }

    /**
     * @return ConnectionInterface[]
     */
    public function getSocketPid()
    {
        return $this->socketPid;
    }

    /**
     * @param int $pid
     *
     * @return ConnectionInterface | null
     */
    public function getSocketByPid(int $pid): ?ConnectionInterface
    {
        if (!array_key_exists($pid, $this->socketPid)) {
            return null;
        }

        return $this->socketPid[$pid];
    }

    /**
     * @return int
     */
    public function getPingPollingInterval(): int
    {
        return $this->pingPollingInterval;
    }

    /**
     * @param int $pingPollingInterval
     */
    public function setPingPollingInterval(int $pingPollingInterval): void
    {
        $this->pingPollingInterval = $pingPollingInterval;
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
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): void
    {
        $this->timeoutSocketWrite = $timeoutSocketWrite;
    }

    /**
     * @return string | null
     */
    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    /**
     * @param string|null $schedule
     */
    public function setSchedule(?string $schedule): void
    {
        $this->schedule = $schedule;
    }

    /**
     * @return int
     */
    public function getStartSecond(): int
    {
        return $this->startSecond;
    }

    /**
     * @param int $startSecond
     */
    public function setStartSecond(int $startSecond): void
    {
        $this->startSecond = $startSecond;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @param bool $daemon
     */
    public function setDaemon(bool $daemon): void
    {
        $this->daemon = $daemon;
    }

    /**
     * @param int $countProcesess
     */
    private function startProcess($countProcesess = 1)
    {
        if ($this->getCountProcesses() >= $this->maxInstance) {
            return;
        }

        do {
            $process = new Process($this->getCommand());
            $process->start($this->loop);
            $this->subscribeToProcessOutput($process, $this->loop);
            $this->addProcess($process);
            $countProcesess --;
        } while($countProcesess > 0);
    }

    /**
     * @param Process $process
     */
    private function restartProcess(Process $process)
    {
        $this->emit(Constants::RESTART_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: restart."]);

        $this->removeProcess($process);
        $this->startProcess();
    }

    /**
     * @param Process $process
     */
    private function removeProcess(Process $process)
    {
        $this->emit(Constants::STOP_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: stop."]);
        $process->removeAllListeners();
        $process->terminate();
        unset($this->processes[$process->getPid()]);
        unset($this->socketPid[$process->getPid()]);
    }

    /**
     * @param Process $process
     * @param $loop
     */
    private function subscribeToProcessOutput(Process $process, $loop): void
    {
        $that = $this;

        $this->emit(Constants::START_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: start."]);

        $process->stdout->on('data', function ($data) use ($process) {
            $data = str_replace("\n", '', $data);

            if (stristr($data, '[ERROR]')) {
                $data = str_replace('[ERROR]', '', $data);
                $this->emit(Constants::ERROR_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}:\n $data"]);

                return;
            }

            if (empty(str_replace("\n", '', $data))) {
                return;
            }

            $this->emit(Constants::CHANNEL_CLIENT_CONSOLE, ["Process '{$this->getName()}' - {$process->getPid()}: $data."]);
        });

        $process->stderr->on('data', function ($data) use ($process, $loop, $that) {
            if (empty(str_replace("\n", '', $data))) {
                return;
            }

            $this->emit(Constants::ERROR_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}:\n $data"]);

            if (!is_null($this->getSchedule()) && !$this->isConsumer()) {
                $this->emit(Constants::ERROR_CRON_PROCESS_EVENT, [$this->getName(), $process->getPid(), $data]);
            }
        });

        $process->on('exit', function($exitCode) use ($process, $loop, $that) {
            if ($exitCode === 0) {
                $this->emit(Constants::EXIT_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: Остановка по команде СТОП."]);
                $that->removeProcess($process);
            } else {
                if (!$this->isDaemon()) {
                    $that->removeProcess($process);
                    return;
                }

                $that->restartProcess($process);
            }
        });

        // логируем что отправили по сокету клиенты
        $this->on(Constants::CHANNEL_CLIENT_WRITE, function ($message) use ($process) {
            if ($message['pid'] !== $process->getPid()) {
                return;
            }

            $message = json_encode($message);
            $this->emit(Constants::CHANNEL_CLIENT_CONSOLE, ["Process '{$this->getName()}' - {$process->getPid()} отправил данные: $message."]);
        });
    }

    private function startScheduleDaemon()
    {
        $scheduler = new CronExpression($this->getSchedule());

        $offsetFunction = function ($scheduler, $offsetFunction) {
            $this->loop->addPeriodicTimer(0.1, function (TimerInterface $offsetTimer) use ($scheduler, $offsetFunction) {
                if ((int) date('s') !== $this->startSecond) {
                    return;
                }

                $this->loop->addPeriodicTimer(60, function (TimerInterface $scheduleTimer) use ($scheduler, $offsetFunction) {
                    if (!$scheduler->isDue()) {
                        return;
                    }

                    if (0 !== $this->getCountProcesses()) {
                        return;
                    }

                    $this->startProcess();

                    if ((int) date('s') !== $this->startSecond) {
                        $this->loop->cancelTimer($scheduleTimer);
                        $offsetFunction($scheduler, $offsetFunction);
                    }
                });

                if ($scheduler->isDue()) {
                    $this->startProcess();
                }

                $this->loop->cancelTimer($offsetTimer);
            });
        };

        $offsetFunction($scheduler, $offsetFunction);
    }
}
