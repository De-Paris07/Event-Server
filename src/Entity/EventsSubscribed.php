<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class EventsSubscribed
 *
 * @ORM\Table(name="event_subscribed",
 *     uniqueConstraints={
 *     @ORM\UniqueConstraint(
 *         name="clientEventType",
 *         columns={"client_id", "event_type_id"}
 *     )}
 * )
 * @ORM\Entity(repositoryClass="App\Repository\EventSubscribedRepository")
 */
class EventsSubscribed
{
    /**
     * @var Client
     *
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="App\Entity\Client", inversedBy="sendedEvents")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     */
    private $client;

    /**
     * @var EventType
     *
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="App\Entity\EventType", inversedBy="subscribedClients")
     * @ORM\JoinColumn(name="event_type_id", referencedColumnName="id", nullable=false)
     */
    private $eventType;

    /**
     * @var integer
     *
     * @ORM\Column(name="priority", type="integer", length=10, nullable=false, options={"comment"="Приоритет получения события сервисом"})
     */
    private $priority;

    /**
     * @var string
     *
     * @ORM\Column(name="channel", type="string", length=50, nullable=true, options={"comment"="Дополнительная очередь сервиса"})
     */
    private $channel;

    /**
     * @var integer
     *
     * @ORM\Column(name="service_priority", type="integer", length=10, nullable=false, options={"comment"="Приоритет доставки эвента до сервиса"})
     */
    private $servicePriority;

    /**
     * @var bool
     *
     * @ORM\Column(name="retry", type="boolean", nullable=false, options={"comment"="Нужно ли повторить сообщение при ошибке обработки на сервисе"})
     */
    private $retry;

    /**
     * @var int
     *
     * @ORM\Column(name="count_retry", type="integer", nullable=false, length=10, options={"comment"="Кол-во попыток повтора сообщения"})
     */
    private $countRetry;

    /**
     * @var int
     *
     * @ORM\Column(name="interval_retry", type="integer", nullable=false, length=10, options={"comment"="Интервал между попытками повтора сообщения"})
     */
    private $intervalRetry;

    /**
     * @var integer
     *
     * @ORM\Column(name="priority_retry", type="integer", length=11, nullable=false, options={"comment"="Приоритет повторного сообщения в очереди"})
     */
    private $priorityRetry;

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     *
     * @return EventsSubscribed
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return EventType
     */
    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    /**
     * @param EventType $eventType
     *
     * @return EventsSubscribed
     */
    public function setEventType(EventType $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param $priority
     *
     * @return EventsSubscribed
     */
    public function setPriority($priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return string
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * @param string | null $channel
     */
    public function setChannel(?string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return int
     */
    public function getServicePriority(): int
    {
        return $this->servicePriority;
    }

    /**
     * @param int $servicePriority
     */
    public function setServicePriority(int $servicePriority): void
    {
        $this->servicePriority = $servicePriority;
    }

    /**
     * @return bool
     */
    public function isRetry(): bool
    {
        return $this->retry;
    }

    /**
     * @param bool $retry
     *
     * @return EventsSubscribed
     */
    public function setRetry(bool $retry): EventsSubscribed
    {
        $this->retry = $retry;

        return $this;
    }

    /**
     * @return int
     */
    public function getCountRetry(): int
    {
        return $this->countRetry;
    }

    /**
     * @param int $countRetry
     *
     * @return EventsSubscribed
     */
    public function setCountRetry(int $countRetry): EventsSubscribed
    {
        $this->countRetry = $countRetry;

        return $this;
    }

    /**
     * @return int
     */
    public function getIntervalRetry(): int
    {
        return $this->intervalRetry;
    }

    /**
     * @param int $intervalRetry
     *
     * @return EventsSubscribed
     */
    public function setIntervalRetry(int $intervalRetry): EventsSubscribed
    {
        $this->intervalRetry = $intervalRetry;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriorityRetry(): int
    {
        return $this->priorityRetry;
    }

    /**
     * @param int $priorityRetry
     *
     * @return EventsSubscribed
     */
    public function setPriorityRetry(int $priorityRetry): EventsSubscribed
    {
        $this->priorityRetry = $priorityRetry;

        return $this;
    }
}
