<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Entity\Event;
use App\Entity\EventsSubscribed;
use App\Entity\EventType;
use App\Entity\UnhandledEventsClient;
use App\Exception\SendDataException;
use App\Job\Job;
use App\Job\RetryJob;
use App\Service\CacheService;
use App\Service\ElasticsearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class RetryDistributeConsumer
 *
 * @package App\Consumer
 */
class RetryDistributeConsumer extends AbstractConsumer
{
    /** @var string $data */
    private $data;

    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;

    /**
     * RetryDistributeConsumer constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param CacheService $cacheService
     * @param ElasticsearchService $elasticsearchService
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        ElasticsearchService $elasticsearchService
    ) {
        parent::__construct($container, $entityManager, $cacheService);

        $this->elasticsearchService = $elasticsearchService;
    }

    /**
     * @param RetryJob $job
     */
    public function consume(Job $job)
    {
        $this->deliveryToServices = [];
        $this->deliveryTime = [];
        $this->clientPriority = [];
        $this->startTime = (new \DateTime())->format('d-m-Y H:i:s.u');
        $events = [];
        $this->data = null;

        $this->checkEvent($job);
        $this->entityManager->refresh($job->getClient());

        switch ($eventId = $job->getRetryEventId()) {
            case 'last':
                $events[] = $job->getClient()->getLastEvent();
                break;
            case 'error':
                $events = $this->entityManager->getRepository(UnhandledEventsClient::class)
                    ->findBy(['client' => $job->getClient()]);
                break;
            default:
                if (empty($job->getRetryEventId())) {
                    break;
                }

                /** @var Event | null $event */
                $events[] = $this->entityManager->getRepository(Event::class)
                    ->findOneBy(['id' => $job->getRetryEventId()]);

                if (!empty($events)) {
                    break;
                }

                $document = $this->elasticsearchService->getDocument($job->getRetryEventId());

                if (empty($document['_source'])
                    || empty($document['_source']['serviceName'])
                    || !isset($document['_source']['eventData'])
                    || empty($document['_source']['eventName'])
                ) {
                    break;
                }

                $event = new Event();
                $event->setData(json_encode([
                    'eventId' => $job->getRetryEventId(),
                    'event' => $document['_source']['eventData'],
                    'eventName' => $document['_source']['eventName'],
                    'serviceName' => $document['_source']['serviceName'],
                    'priority' => 1024,
                ], 128 | 256));

                $eventType = $this->entityManager->getRepository(EventType::class)
                    ->findOneBy(['name' => $document['_source']['eventName']]);
                $event->setType($eventType);

                array_push($events, $event);
        }

        if (empty($events)) {
            $this->buildLog($job, "Не найдено ни одного события для повтора.");

            return;
        }

        $this->deliveryToServices[] = $job->getClient()->getName();

        foreach ($events as $event) {
            if ($event instanceof UnhandledEventsClient) {
                $event = $event->getEvent();
            }

            $this->handleEvent($event, $job);
        }

        $this->entityManager->clear();
    }

    /**
     * @param Event $event
     * @param RetryJob $job
     */
    private function handleEvent(Event $event, RetryJob $job)
    {
        $this->data = $event->getData();
        $eventsSubscribed = $this->entityManager
            ->getRepository(EventsSubscribed::class)
            ->findOneBy([
                'client' => $job->getClient(),
                'eventType' => $event->getType()
            ]);
        $channel = $eventsSubscribed->getChannel() ?? $job->getClient()->getDeliveryAddress();

        try {
            $this->sendDataToQueue($event->getData(), $channel, $job->getRetryPriority());
        } catch (SendDataException $sendDataException) {

        }

        $this->buildLog($job);
    }

    /**
     * @param RetryJob $job
     */
    private function checkEvent(RetryJob $job)
    {
        if (!$job->getData()) {
            throw new NotFoundHttpException("Original data not found");
        }

        if (!$job->getClient()) {
            throw new NotFoundHttpException("Client not found by token '{$job->getServerToken()}'");
        }
    }

    /**
     * @param string $data
     * @param string $tube
     * @param int $priority
     *
     * @return int
     *
     * @throws SendDataException
     */
    private function sendDataToQueue(string $data, string $tube, int $priority)
    {
        $exceptions = [];
        $this->clientPriority[$tube] = $priority;

        for ($attempt = 0; $attempt < $_ENV['SEND_DATA_ATTEMPTS_COUNT']; $attempt++) {
            try {
                $queueJob = $this->pheanstalk->useTube($tube)->put($data, $priority);
                $this->deliveryTime[$tube] = (new \DateTime())->format('d-m-Y H:i:s.u');

                return $queueJob;
            } catch (\Exception $e) {
                $exceptions[] = $e;
                continue;
            }
        }

        throw new SendDataException($exceptions);
    }

    /**
     * @param RetryJob $job
     * @param string|null $error
     */
    private function buildLog(RetryJob $job, string $error = null)
    {
        if (is_null($this->data)) {
            $this->data = $job->getData();
        }

        $data = json_decode($this->data, true);
        $data['deliveryToServices'] = $this->deliveryToServices;
        $data['eventData'] = $data['event'];
        $data['eventServerProcessingTime'] = new \DateTime();
        $data['deliveryTime'] = json_encode(['start' => $this->startTime] + $this->deliveryTime, 128 | 256);
        $data['retryEventId'] = $job->getRetryEventId();
        $data['retryId'] = $job->getEventId();

        if (!empty($this->clientPriority)) {
            $data['clientPriority'] = json_encode($this->clientPriority, 128 | 256);
        }

        if (!is_null($error)) {
            $data['retryError'] = $error;
        }

        unset($data['event']);
        unset($data['priority']);
        unset($data['serverToken']);

        $this->logger->info('', $data);
    }
}
