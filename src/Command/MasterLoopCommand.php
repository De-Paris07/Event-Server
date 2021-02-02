<?php

namespace App\Command;

use App\Loop\CommandLoop;
use App\Loop\LoopTrait;
use App\Service\TelegramLogger;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MasterLoopCommand
 *
 * @package App\Command
 */
class MasterLoopCommand extends ContainerAwareCommand
{
    use LoopTrait;

    const COMMANDS = [
        'distributes' => 'exec php bin/console events-distribute:start',
        'server-query' => 'exec php bin/console event-query:start',
        'server-retry' => 'exec php bin/console event-retry:start',
        'server-socket' => 'exec php bin/console event:server:socket',
        'success' => 'exec php bin/console success-event-processing:start',
        'fail' => 'exec php bin/console fail-event-processing:start',
        'dispatcher' => 'exec php bin/console event:dispatch:retry',
    ];

    const CRON_COMMANDS = [
        'check-elastic-data' => 'exec php bin/console event:check:elastic:data',
//        'elastic-reindex' => 'exec php bin/console event:elastic:reindex',
    ];

    /** @var ContainerInterface $container */
    private $container;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;
    
    /** @var ConnectionInterface[] $connections */
    private $connections;

    /**
     * MasterLoopCommand constructor.
     *
     * @param ContainerInterface $container
     * @param TelegramLogger $telegramLogger
     */
    public function __construct(ContainerInterface $container, TelegramLogger $telegramLogger)
    {
        parent::__construct();
        $this->container = $container;
        $this->telegramLogger = $telegramLogger;
    }

    protected function configure()
    {
        $this->setName('event:loop')
            ->setDescription('Мастер процесс управления всеми демонами обработки событий')
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'output',
                true
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '200M');

        $this->initServer($input, $output);
        
        $commands = $this->getCommands();
        
        $this->addCommands($commands);
        $this->startCommands();
        $this->loop->addPeriodicTimer(600, function (TimerInterface $timer) {
            echo (new \DateTime())->format('d-m-Y H:i:s.u') . " Process 'master' - " . getmypid() . ": memory -> " . memory_get_peak_usage(true) / 1024 / 1024 . PHP_EOL;
        });
        
        try {
            $this->loop->run();
        } catch (\Throwable $exception) {
            $this->stopCommands();
            throw $exception;
        }
    }

    /**
     * @return array
     */
    private function getCommands()
    {
        $commands = [];

        foreach (self::COMMANDS as $name => $commandLine) {
            $command = new CommandLoop();
            $command->setName($name);
            $command->setCommand($commandLine);
            $command->setConsumer(true);
            $command->setDaemon(true);
            $command->setCountJob(10);
            $command->setIntervalTick(0.05);
            $command->setMinInstance(1);
            $command->setMaxInstance(5);
            $command->setTimeoutCreate(3);
            $command->setUseMaxMemory(50);
            $command->setPingPollingInterval(300);
            $command->setTimeoutSocketWrite(60);

            if ('dispatcher' === $name) {
                $command->setIntervalTick(1);
                $command->setCountJob(1000);
                $command->setConsumer(false);
            }

            if ('server-socket' === $name) {
                $command->setIntervalTick(1);
                $command->setMaxInstance(1);
                $command->setConsumer(false);
            }

            if ('server-retry' === $name) {
                $command->setMaxInstance(1);
            }

            $commands[] = $command;
        }

        foreach (self::CRON_COMMANDS as $name => $commandLine) {
            $command = new CommandLoop();
            $command->setName($name);
            $command->setCommand($commandLine);
            $command->setConsumer(false);
            $command->setDaemon(false);
            $command->setCountJob(10);
            $command->setIntervalTick(0.05);
            $command->setMinInstance(1);
            $command->setMaxInstance(1);
            $command->setTimeoutCreate(3);
            $command->setUseMaxMemory(50);
            $command->setPingPollingInterval(300);
            $command->setTimeoutSocketWrite(60);

            if ('check-elastic-data' === $name) {
                // каждый день в час ночи
                $command->setSchedule('0 1 * * *');
            }

            if ('elastic-reindex' === $name) {
                // в час ночи в начале месяца
                $command->setSchedule('0 1 1 * *');
            }

            $commands[] = $command;
        }

        return $commands;
    }
}
