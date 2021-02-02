<?php

declare(strict_types=1);

namespace App\Command;

use App\Consumer\Consumer;
use App\Consumer\EventDistributeConsumer;
use App\Job\Job;
use App\Loop\LoopTrait;
use App\Service\TelegramLogger;
use Doctrine\ORM\ORMException;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Exception\SocketException;
use Pheanstalk\Pheanstalk;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AbstractClientCommand
 *
 * @package App\Command
 */
abstract class AbstractClientCommand extends ContainerAwareCommand
{
    use LoopTrait;

    /**
     * @var Pheanstalk $pheanstalk
     */
    protected $pheanstalk;

    /**
     * @var EventDistributeConsumer
     */
    protected $consumer;

    /**
     * @var Job
     */
    protected $job;

    /** @var TelegramLogger $telegramLogger */
    protected $telegramLogger;

    /**
     * @return string
     */
    abstract protected function getTube(): string;

    /**
     * @return string | null
     */
    abstract protected function getStartMessage(): ?string;

    /**
     * AbstractClientCommand constructor.
     *
     * @param Consumer $consumer
     * @param Job $job
     * @param TelegramLogger $telegramLogger
     * @param string|null $name
     */
    public function __construct(Consumer $consumer, Job $job, TelegramLogger $telegramLogger, string $name = null)
    {
        $this->consumer = $consumer;
        $this->job = $job;
        $this->telegramLogger = $telegramLogger;

        parent::__construct($name);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!is_null($startMessage = $this->getStartMessage())) {
            $io->success($startMessage);
        }

        $this->pheanstalk = new Pheanstalk($_ENV['PHEANSTALK_SERVER']);
        $this->pheanstalk->useTube($this->getTube());

        $countJobReady = null;
        $counter = 0;
        $this->initClient();

        $handler = function () use (&$countJobReady, $input, $output, &$counter, $io) {
            try {
                if ($countJobReady !== $stat = $this->pheanstalk->statsTube($this->getTube())['current-jobs-ready']) {
                    if ($this->socket) {
                        $this->setCountJobReadyClient($stat);
                    }
                    $io->success($stat = $this->pheanstalk->statsTube($this->getTube())['current-jobs-ready']);
                    $countJobReady = $stat;
                }

                $this->checkConnection();

                $job = $this->pheanstalk->watch($this->getTube())->ignore('default')->reserve(5);

                if (!($job instanceof \Pheanstalk\Job)) {
                    return;
                }

                $this->job->setJob($job);

                $this->telegramLogger->setCurrentEvent($this->job->getEventId(), $this->job->getEventName());

                $this->consumer->consume($this->job);

                $this->pheanstalk->delete($job);

                ++$counter;
                $memoryUsage = memory_get_peak_usage(true);
                $output->writeln(sprintf('Job "%d" of tube "%s" completed. Processing tube exhausted memory with "%sMB" of usage.', $counter, $this->getTube(), ($memoryUsage / 1024 / 1024)));

                unset($memoryUsage, $job);
                gc_collect_cycles();
            } catch (SocketException $socketException) {
                $io->error($socketException->getMessage());

                exit(50);
            } catch (ServerException $exception) {
                $io->error($exception->getMessage());
                exit(50);
            } catch (ORMException $exception) {
                $this->telegramLogger->setFail($exception);
                throw $exception;
            } catch (\PDOException $exception)  {
                $io->error($exception->getMessage());
                $this->telegramLogger->setFail($exception);

                return;
            } catch (\Exception | \Throwable $exception) {
                if (!isset($job)) {
                    $io->error($exception->getMessage());

                    return;
                }

                try {
                    $this->pheanstalk->bury($job);
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    gc_collect_cycles();

                    return;
                }

                $io->error($exception->getMessage());

                $this->telegramLogger->setFail($exception);
                gc_collect_cycles();
            }
        };

        $this->addJob($handler);
        $this->start();
    }

    private function checkConnection()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        if ($em->getConnection()->ping() === false) {
            $em->getConnection()->close();
            $em->getConnection()->connect();
        }
    }
}
