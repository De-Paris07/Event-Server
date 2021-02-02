<?php

namespace App\Loop;

use Evenement\EventEmitterTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait LoopTrait
{
    use SocketTrait, EventEmitterTrait;
    
    /** @var ContainerInterface $container */
    private $container;

    /** @var LoopInterface $lopp */
    protected $loop;

    /** @var CommandLoop[] $commands */
    private $commands = [];

    /** @var bool $stop */
    private $stop = false;
    
    /** @var array | null $clientSettings */
    private $clientSettings;

    /** @var \Closure[] $clientJobs */
    private $clientJobs = [];
    
    /** @var Screen $screen */
    private $screen;

    /**
     * @required
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param \Closure $closure
     */
    public function addJob(\Closure $closure)
    {
        $this->clientJobs[] = $closure;
    }
    
    public function start()
    {
        $this->loop->run();
    }

    /**
     * @param CommandLoop $command
     *
     * @return CommandLoop
     */
    public function addCommand(CommandLoop $command): CommandLoop
    {
        $this->commands[$command->getCommand()] = $command;
        $command->setLoop($this->loop);

        $command->on(Constants::CHANNEL_CLIENT_CONSOLE, function ($payload) {
            $this->screen->info($payload);
        });

        $command->on(Constants::START_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::STOP_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::RESTART_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::ERROR_PROCESS_EVENT, function ($payload) {
            $this->screen->warning($payload);
        });

        $command->on(Constants::ERROR_CRON_PROCESS_EVENT, function (string $cronName, int $pid, string $error) {
            $this->telegramLogger->setFail(new \Exception($error), "Cron: {$cronName}[$pid]\n");
        });
        
        $command->on(Constants::EXIT_PROCESS_EVENT, function ($payload) {
            $this->screen->info($payload);
        });

        return $command;
    }

    /**
     * @param CommandLoop[] $commands
     */
    public function addCommands(array $commands)
    {
        foreach ($commands as $command) {
            $this->addCommand($command);
        }
    }

    public function startCommands()
    {
        foreach ($this->commands as $command) {
            $command->start();
        }
    }
    
    public function stopCommands()
    {
        foreach ($this->commands as $command) {
            $command->stop();
        }
    }

    /**
     * @param int | null $pid
     *
     * @return CommandLoop | null
     */
    public function getCommandByPid(?int $pid)
    {
        if (is_null($pid)) {
            return null;
        }
        
        foreach ($this->commands as $command) {
            if ($command->getProcess($pid)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function initServer(InputInterface $input, OutputInterface $output)
    {
        $this->loop = LoopFactory::getLoop();
        $this->screen = new Screen(new SymfonyStyle($input, $output));
        $this->initSocketServer($this->loop);

        $stopHandler = function (int $signal) {
            $this->server->close();
            exit($signal);
        };

        $this->loop->addSignal(SIGINT, $stopHandler);
        $this->loop->addSignal(SIGTERM, $stopHandler);
        $this->loop->addSignal(SIGHUP, $stopHandler);
        $this->loop->addSignal(SIGBUS, $stopHandler);
        $this->loop->addSignal(SIGSYS, $stopHandler);
        $this->loop->addSignal(SIGTRAP, $stopHandler);
        $this->loop->addSignal(SIGPIPE, $stopHandler);
        $this->loop->addSignal(SIGTSTP, $stopHandler);
        
        $this->loop->addPeriodicTimer(1, function ($timer) {
            if ((memory_get_peak_usage(true) / 1024 / 1024) > 100) {
                $this->stopCommands();
                $this->server->close();
                $this->loop->cancelTimer($timer);
                exit();
            }
        });
    }

    private function initClient()
    {
        $this->loop = LoopFactory::getLoop();
        $this->initSocketClient($this->loop);

        $stopHandler = function (int $signal) {
            $this->stop = true;
        };

        $this->loop->addSignal(SIGINT, $stopHandler);
        $this->loop->addSignal(SIGTERM, $stopHandler);
        $this->loop->addSignal(SIGHUP, $stopHandler);
        $this->loop->addSignal(SIGBUS, $stopHandler);
        $this->loop->addSignal(SIGSYS, $stopHandler);
        $this->loop->addSignal(SIGTRAP, $stopHandler);
        $this->loop->addSignal(SIGPIPE, $stopHandler);
        $this->loop->addSignal(SIGTSTP, $stopHandler);

        $this->on(Constants::SOCKET_CHANNEL_CLIENT_SETTINGS, function (SocketMessage $message) {
            $this->clientSettings = $message->getData();
        });

        // таймер на ожидание от сервера настроек команды.
        $settingTimer = $this->loop->addPeriodicTimer(0.01, function ($timer) {
            if (is_null($this->clientSettings) || empty($this->clientJobs)) {
                return;
            }

            foreach ($this->clientJobs as $job) {
                $this->loop->addPeriodicTimer($this->clientSettings['interval'], function ($timer) use ($job) {
                    $this->safeExit();
                    
                    call_user_func($job, $timer);
                    
                    $this->safeExit();
                    
                    if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memorySetting = $this->clientSettings['maxMemory']) {
                        echo "Процесс превысил выделенное количество памяти в $memorySetting Mb";
                        exit(50);
                    }
                });
            }
            
            $this->loop->cancelTimer($timer);
        });

        // если через секунду соединение с сервером не установилось, то сбрасываем таймер на ожидание настроек, и задаем дефолтные.
        $this->loop->addTimer(1, function ($timer) use ($settingTimer) {
            if (!$this->socket) {
                $memoryUse = 50;
                
                foreach ($this->clientJobs as $job) {
                    $this->loop->addPeriodicTimer(0.05, function ($timer) use ($job, $memoryUse) {
                        $this->safeExit();

                        call_user_func($job, $timer);

                        $this->safeExit();

                        if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memoryUse) {
                            echo "Процесс превысил выделенное количество памяти в $memoryUse Mb";
                            exit(50);
                        }
                    });
                }

                // если через 3 секунды подключились к сокету, но мастер не отдал настройки запуска, пробуем перезапуститься
                if ($this->socket && is_null($this->clientSettings)) {
                    echo 'Мастер за 3 секунды не отдал настройки, перезапускаемся ' . PHP_EOL;
                    exit(50);
                }
            }
            
            $this->loop->cancelTimer($settingTimer);
        });
    }

    private function safeExit()
    {
        if ($this->stop) {
            echo 'Завершение процесса по команде "СТОП"';
            exit();
        }
    }
}
