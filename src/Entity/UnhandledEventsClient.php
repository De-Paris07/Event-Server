<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UnhandledEventsClientRepository")
 * @ORM\Table(name="unhandle_event",
 *     uniqueConstraints={
 *     @ORM\UniqueConstraint(
 *         name="clientEvent",
 *         columns={"client_id", "event_id"}
 *     )}
 *  )
 */
class UnhandledEventsClient
{
    /**
     * @var Client
     *
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="App\Entity\Client", inversedBy="unhandlesEvents")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     */
    private $client;

    /**
     * @var Event
     *
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="App\Entity\Event")
     * @ORM\JoinColumn(name="event_id", referencedColumnName="id", nullable=false)
     */
    private $event;

    /**
     * @ORM\Column(name="count_retry", type="integer", length=10, nullable=false)
     */
    private $countRetry = 0;

    /**
     * @ORM\Column(type="datetime", options={"comment"="Дата создания"})
     */
    private $created_at;

    /**
     * UnhandledEventsClient constructor.
     *
     */
    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
    }

    /**
     * Get client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set client
     *
     * @param Client $client
     * @return UnhandledEventsClient
     */
    public function setClient(Client $client): UnhandledEventsClient
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get event
     *
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }

    /**
     * Set event
     *
     * @param Event | null $event
     * @return UnhandledEventsClient
     */
    public function setEvent(?Event $event): UnhandledEventsClient
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Get countRetry
     *
     * @return int
     */
    public function getCountRetry(): int
    {
        return $this->countRetry;
    }

    /**
     * Set countRetry
     *
     * @param int $countRetry
     * @return UnhandledEventsClient
     */
    public function setCountRetry(int $countRetry): UnhandledEventsClient
    {
        $this->countRetry = $countRetry;

        return $this;
    }

    /**
     * Get created_at
     *
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set created_at
     *
     * @param mixed $created_at
     * @return UnhandledEventsClient
     */
    public function setCreatedAt($created_at): UnhandledEventsClient
    {
        $this->created_at = $created_at;

        return $this;
    }
}
