<?php

namespace App\Job;

use App\Entity\Client;
use App\Entity\Event;
use App\Entity\EventsSubscribed;
use App\Entity\EventType;
use App\Entity\UnhandledEventsClient;
use App\Repository\ClientRepository;
use App\Repository\EventRepository;
use App\Repository\EventSubscribedRepository;
use App\Repository\EventTypeRepository;
use App\Repository\UnhandledEventsClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pheanstalk\Job;

/**
 * Class BaseJob
 *
 * @package App\Job
 */
abstract class BaseJob implements \App\Job\Job
{
    /**
     * @var string
     */
    private $originalData;

    /** @var Client $client */
    private $client;

    /** @var EventType $eventType */
    private $eventType;

    /**
     * @var array
     */
    protected $data;

    /** @var EntityManagerInterface $entityManager */
    protected $entityManager;

    /**
     * BaseJob constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface  $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Job $job
     */
    public function setJob(Job $job): void
    {
        $this->clear();
        $this->originalData = $job->getData();
        $this->data = json_decode($job->getData(), true);
    }

    /**
     * @return string | null
     */
    public function getData(): ?string
    {
        return $this->originalData;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->clear();
        $this->data = $data;
        $this->originalData = json_encode($data);
    }

    /**
     * @return string
     */
    public function getEventId(): string
    {
        return $this->data['eventId'];
    }

    /**
     * @return string | null
     */
    public function getEventData(): ?string
    {
        return isset($this->data['event']) ? $this->data['event'] : null;
    }

    /**
     * @return string | null
     */
    public function getEventName(): ?string
    {
        return isset($this->data['eventName']) ? $this->data['eventName'] : null;
    }

    /**
     * @return string | null
     */
    public function getServiceName(): ?string
    {
        return isset($this->data['serviceName']) ? $this->data['serviceName'] : null;
    }

    /**
     * @return string | null
     */
    public function getServerToken(): ?string
    {
        return isset($this->data['serverToken']) ? $this->data['serverToken'] : null;
    }

    /**
     * @return Event | null
     */
    public function getEvent(): ?Event
    {
        return $this->getEventRepository()->findOneBy(['id' => $this->getEventId()]);
    }

    /**
     * @return Client | null
     */
    public function getClient(): ?Client
    {
        if (is_null($this->getServerToken())) {
            return null;
        }

        if (!is_null($this->client)) {
            return $this->client;
        }

        return $this->client = $this->getClientRepository()->findOneBy(['serverToken' => $this->getServerToken()]);
    }

    /**
     * @return EventType | null
     */
    public function getEventType(): ?EventType
    {
        if (is_null($this->getEventName())) {
            return null;
        }

        if (!is_null($this->eventType)) {
            return $this->eventType;
        }

        return $this->eventType = $this->getEventTypeRepository()->findOneBy(['name' => $this->getEventName()]);
    }

    /**
     * @return ClientRepository
     */
    public function getClientRepository()
    {
        return $this->entityManager->getRepository(Client::class);
    }

    /**
     * @return EventRepository
     */
    public function getEventRepository()
    {
        return $this->entityManager->getRepository(Event::class);
    }

    /**
     * @return EventSubscribedRepository
     */
    public function getEventSubscribedRepository()
    {
        return $this->entityManager->getRepository(EventsSubscribed::class);
    }

    /**
     * @return UnhandledEventsClientRepository
     */
    public function getUnhandledEventsClientRepository()
    {
        return $this->entityManager->getRepository(UnhandledEventsClient::class);
    }

    /**
     * @return EventTypeRepository
     */
    public function getEventTypeRepository()
    {
        return $this->entityManager->getRepository(EventType::class);
    }

    protected function clear()
    {
        $this->client =
        $this->eventType = null;
    }
}
