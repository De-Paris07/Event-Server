<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RetryEventRepository")
 * @ORM\Table(name="retry_event")
 */
class RetryEvent
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var bool
     *
     * @ORM\Column(name="in_work", type="boolean", nullable=false)
     */
    private $inWork = false;

    /**
     * @var integer
     *
     * @ORM\Column(name="count_attempts", type="integer", length=3, nullable=false)
     */
    private $countAttempts = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="event_id", type="string", length=255, nullable=false)
     */
    private $eventId;

    /**
     * @var int
     *
     * @ORM\Column(name="delivery_type", type="integer", length=3, nullable=false)
     */
    private $deliveryType;

    /**
     * @var string
     *
     * @ORM\Column(name="delivery_address", type="string", length=255, nullable=false)
     */
    private $deliveryAddress;

    /**
     * @var int
     *
     * @ORM\Column(name="priority", type="integer", length=20)
     */
    private $priority;

    /**
     * @var string
     *
     * @ORM\Column(name="event_name", type="string", length=255, nullable=false)
     */
    private $eventName;

    /**
     * @var string
     *
     * @ORM\Column(name="data", type="text", nullable=false)
     */
    private $data;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", length=6, nullable=false)
     */
    private $created;

    /**
     * @var integer
     *
     * @ORM\Column(name="retry_date", type="integer", nullable=true)
     */
    private $retryDate;

    /**
     * Event constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return bool
     */
    public function isInWork(): bool
    {
        return $this->inWork;
    }

    /**
     * @param bool $inWork
     */
    public function setInWork(bool $inWork): void
    {
        $this->inWork = $inWork;
    }

    /**
     * @return int
     */
    public function getCountAttempts(): int
    {
        return $this->countAttempts;
    }

    /**
     * @param int $countAttempts
     */
    public function setCountAttempts(int $countAttempts): void
    {
        $this->countAttempts = $countAttempts;
    }

    /**
     * @return string
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * @param string $eventId
     */
    public function setEventId(string $eventId): void
    {
        $this->eventId = $eventId;
    }

    /**
     * @return int
     */
    public function getDeliveryType(): int
    {
        return $this->deliveryType;
    }

    /**
     * @param int $deliveryType
     */
    public function setDeliveryType(int $deliveryType): void
    {
        $this->deliveryType = $deliveryType;
    }

    /**
     * @return string
     */
    public function getDeliveryAddress(): string
    {
        return $this->deliveryAddress;
    }

    /**
     * @param string $deliveryAddress
     */
    public function setDeliveryAddress(string $deliveryAddress): void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * @param string $eventName
     */
    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    /**
     * @return int
     */
    public function getRetryDate(): ?int
    {
        return $this->retryDate;
    }

    /**
     * @param int $retryDate
     */
    public function setRetryDate(int $retryDate): void
    {
        $this->retryDate = $retryDate;
    }
}
