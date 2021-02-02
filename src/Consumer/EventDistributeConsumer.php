<?php

namespace App\Consumer;

use App\Entity\Client;
use App\Entity\EventsSubscribed;
use App\Entity\RetryEvent;
use App\Exception\SendDataException;
use App\Handler\EventHandler;
use App\Job\EventJob;
use App\Job\Job;
use App\Service\CacheService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EventDistributeConsumer
 *
 * @package App\Consumer
 */
class EventDistributeConsumer extends AbstractConsumer
{
    /**
     * @var EventHandler
     */
    private $eventHandler;

    /** @var Client $client */
    private $client;

    /**
     * EventDistributeConsumer constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param CacheService $cacheService
     * @param EventHandler $eventHandler
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        EventHandler $eventHandler
    ) {
        parent::__construct($container, $entityManager, $cacheService);

        $this->eventHandler = $eventHandler;
    }

    /**
     * @param EventJob $job
     *
     * @throws \Exception
     */
    public function consume(Job $job)
    {
        $this->startTime = (new DateTime())->format('d-m-Y H:i:s.u');
        $eventType = $job->getEventType();
        $this->deliveryToServices =
        $this->deliveryTime =
        $this->clientPriority = [];

        if (!$eventType) {
            $this->deliveryToServices[] = 'Данный тип события не зарегистрирован';
            $this->buildLog($job);

            return;
        }

        /*
         * Дано:
         * Сервисы обработки событий запущены. Новый клиент регистрируется и подписывается на события.
         * В очередь приходит новое событие, на которое подписан клиент.
         *
         * Без нижеследующей строки новый клиент не получит событие, а его получят только клиенты
         * регистрировавшиеся и подписавшиеся на события до запуска сервисов обработки событий.
         * */
        $this->entityManager->refresh($eventType);
        $this->eventHandler->checkEvent($job);

        $queryBuilder = $this->entityManager->getRepository(EventsSubscribed::class)
            ->createQueryBuilder('eventsSubscribed')
            ->where('eventsSubscribed.eventType = :type')
            ->orderBy('eventsSubscribed.servicePriority', 'DESC')
            ->setParameter('type', $eventType);

        if ($job->isHistoryEvent()) {
            $queryBuilder
                ->leftJoin('eventsSubscribed.client', 'client')
                ->andWhere('client.receiveHistoricalData = true');
        }

        /** @var EventsSubscribed[] $eventsSubscribed */
        $eventsSubscribed = $queryBuilder->getQuery()->getResult();

        if (empty($eventsSubscribed) && 0 === count($eventsSubscribed)) {
            $this->deliveryToServices[] = 'На это событие нет подписчиков';
            $this->buildLog($job);

            return;
        }

        foreach ($eventsSubscribed as $eventSubscribe) {
            $this->deliveryToServices[] = $eventSubscribe->getClient()->getName();
        }

        foreach ($eventsSubscribed as $eventSubscribe) {
            $this->client = $eventSubscribe->getClient();

            switch ($this->client->getDeliveryType()) {
                case Client::DELIVERY_TYPE_QUEUE:
                    $channel = $eventSubscribe->getChannel() ?? $this->client->getDeliveryAddress();
                    $this->sendDataToQueue($job, $channel, $eventSubscribe->getPriority());
                    break;
            }
        }

        $this->buildLog($job);

        $event = $this->eventHandler->create([
            'id' => $job->getEventId(),
            'client' => $job->getClient(),
            'eventType' => $eventType,
            'originalData' => $job->getData(),
        ]);

        foreach ($eventsSubscribed as $eventSubscribe) {
            $eventSubscribe->getClient()->setLastEvent($event);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param EventJob $job
     * @param string $tube
     * @param int $priority
     * @param bool $isSave
     *
     * @return EventJob|int
     *
     * @throws SendDataException
     * @throws \Exception
     */
    public function sendDataToQueue(EventJob $job, string $tube, int $priority, $isSave = true)
    {
        $exceptions = [];
        $data = $this->getData($job, $priority);
        $this->clientPriority[$tube] = $priority;

        for ($attempt = 0; $attempt < $_ENV['SEND_DATA_ATTEMPTS_COUNT']; $attempt++) {
            try {
                $queueJob = $this->pheanstalk->useTube($tube)->put(json_encode($data), $priority);
                $this->deliveryTime[$tube] = (new DateTime())->format('d-m-Y H:i:s.u');

                return $queueJob;
            } catch (\Exception $e) {
                $exceptions[] = $e;
                continue;
            }
        }

        if ($isSave) {
            $this->setRetryEvent($job, $this->client, $priority);
        }

        throw new SendDataException($exceptions);
    }

    /**
     * @param EventJob $job
     *
     * @throws \Exception
     */
    private function buildLog(EventJob $job)
    {
        $data = $this->getData($job);
        $data['deliveredToServices'] = $this->deliveryToServices;
        $data['eventData'] = $data['event'];
        $data['eventServerProcessingTime'] = new DateTime();
        $data['deliveryTime'] = json_encode(['start' => $this->startTime] + $this->deliveryTime, 128 | 256);

        if (!empty($this->clientPriority)) {
            $data['clientPriority'] = json_encode($this->clientPriority, 128 | 256);
        }

        unset($data['event']);
        unset($data['priority']);
        $this->logger->info('', $data);
    }

    /**
     * @param EventJob $job
     * @param int $priority
     *
     * @return array
     */
    private function getData(EventJob $job, $priority = 0)
    {
        return [
            'eventId' => $job->getEventId(),
            'event' => $job->getEventData(),
            'eventName' => $job->getEventName(),
            'serviceName' => $job->getServiceName(),
            'priority' => $priority,
        ];
    }

    /**
     * @param EventJob $job
     * @param Client $client
     * @param int $priority
     *
     * @throws \Exception
     */
    private function setRetryEvent(EventJob $job, Client $client, int $priority)
    {
        $retryEvent = new RetryEvent();
        $retryEvent->setEventName($job->getEventName());
        $retryEvent->setEventId($job->getEventId());
        $retryEvent->setDeliveryType($client->getDeliveryType());
        $retryEvent->setDeliveryAddress($client->getDeliveryAddress());
        $retryEvent->setData($job->getData());
        $retryEvent->setPriority($priority);

        $this->entityManager->persist($retryEvent);
        $this->entityManager->flush();
    }
}
