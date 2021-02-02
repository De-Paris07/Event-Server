<?php

namespace App\Command;

use App\Consumer\EventDistributeConsumer;
use App\Entity\RetryEvent;
use App\Exception\SendDataException;
use App\Job\EventJob;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use App\Loop\LoopTrait;
use App\Service\TelegramLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RetryDispatchCommand extends Command
{
    use LoopTrait;

    const COUNT_RETRY = 100;
    const COUNT_RETRY_FOR_NOTIFICATION = 3;
    const TIMEOUT_BEFORE_NOTIFICATION = 10;
    const TIMEOUT_AFTER_NOTIFICATION = 60;
    
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var array $retryConfig */
    private $retryConfig;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;

    /** @var EventDistributeConsumer $eventDistributeConsumer */
    private $eventDistributeConsumer;

    /**
     * @var EventJob
     */
    private $eventJob;

    /**
     * RetryDispatchCommand constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param TelegramLogger $telegramLogger
     * @param EventDistributeConsumer $eventDistributeConsumer
     * @param EventJob $eventJob
     * @param string|null $name
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        TelegramLogger $telegramLogger,
        EventDistributeConsumer $eventDistributeConsumer,
        EventJob $eventJob,
        string $name = null
    ) {
        parent::__construct($name);

        $this->eventJob = $eventJob;
        $this->entityManager = $entityManager;
        $this->telegramLogger = $telegramLogger;
        $this->eventDistributeConsumer = $eventDistributeConsumer;
    }

    protected function configure()
    {
        $this->setName('event:dispatch:retry')
            ->setDescription('Команда для повторной отправки событий в очередь');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initClient();
        $limit = 100;
        $offset = 0;
        
        $handler = function () use ($input, $output, &$offset, $limit) {
            $count = $this->entityManager->getRepository(RetryEvent::class)
                ->createQueryBuilder('event')
                ->select('count(event.id)')
                ->getQuery()
                ->getSingleScalarResult();

            if ($this->socket) {
                $this->setCountJobReadyClient((int) $count);
            }

            $this->safeExit();
            $this->entityManager->beginTransaction();

            /** @var RetryEvent[] $events */
            $events = $this->entityManager->getRepository(RetryEvent::class)
                ->createQueryBuilder('event')
                ->andWhere('event.countAttempts < :count')
                ->andWhere('event.inWork = :inWork')
                ->setParameters(['count' => self::COUNT_RETRY, 'inWork' => false])
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getResult();

            foreach ($events as $event) {
                $event->setInWork(true);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            if (empty($events)) {
                $offset = 0;

                return;
            }

            foreach ($events as $eventDb) {
                try {
                    $count = $eventDb->getCountAttempts();
                    $currentCount = $count + 1;
                    $time = (new \DateTime())->getTimestamp();

                    // если первая попытка и текущее время меньше времени создания + время ожидания до первой нотификации
                    if (0 === $count && (new \DateTime())->getTimestamp() < ($eventDb->getCreated()->getTimestamp() + self::TIMEOUT_BEFORE_NOTIFICATION)) {
                        $eventDb->setInWork(false);
                        $this->entityManager->flush();
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount <= self::COUNT_RETRY_FOR_NOTIFICATION && $time < ($eventDb->getRetryDate() + self::TIMEOUT_BEFORE_NOTIFICATION)) {
                        $eventDb->setInWork(false);
                        $this->entityManager->flush();
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount > self::COUNT_RETRY_FOR_NOTIFICATION && $time < ($eventDb->getRetryDate() + self::TIMEOUT_AFTER_NOTIFICATION)) {
                        $eventDb->setInWork(false);
                        $this->entityManager->flush();
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount === self::COUNT_RETRY_FOR_NOTIFICATION && $time < ($eventDb->getRetryDate() + self::TIMEOUT_BEFORE_NOTIFICATION)) {
                        $eventDb->setInWork(false);
                        $this->entityManager->flush();
                        continue;
                    }

                    $this->eventJob->setData(json_decode($eventDb->getData(), true));

                    $this->eventDistributeConsumer->sendDataToQueue($this->eventJob, $eventDb->getDeliveryAddress(), $eventDb->getPriority(), false);

                    $eventId = $eventDb->getEventId();
                    $this->entityManager->remove($eventDb);
                    $this->entityManager->flush();

                    $output->writeln("Event id: $eventId successfully queued with $currentCount attempts");
                } catch (SendDataException $producerException) {
                    $eventDb->setCountAttempts($currentCount);
                    $eventDb->setRetryDate((new \DateTime())->getTimestamp());
                    $eventDb->setInWork(false);
                    $this->entityManager->flush();

                    if ($currentCount === self::COUNT_RETRY_FOR_NOTIFICATION || $currentCount === self::COUNT_RETRY) {
                        $countRetry = self::COUNT_RETRY;
                        $this->telegramLogger->setCurrentEvent($eventDb->getEventId(), $eventDb->getEventName());
                        $this->telegramLogger->setFail($producerException, " Не удалось поставить событие в очередь!!! \nКоличество попыток: $currentCount из $countRetry \n");
                    }
                } catch (ORMException $exception) {
                    $eventDb->setInWork(false);
                    $this->entityManager->flush();
                    throw $exception;
                } catch (\Exception | \Throwable $exception) {
                    $eventDb->setInWork(false);
                    $this->entityManager->flush();
                    $this->telegramLogger->setFail($exception);
                }
            }

            $this->entityManager->clear(RetryEvent::class);

            $offset = $offset + $limit;
        };
        
        $this->addJob($handler);
        $this->start();
    }
}
