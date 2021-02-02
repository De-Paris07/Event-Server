<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EventRepository")
 * @ORM\Table(name="event")
 */
class Event
{
    /**
     * @var string
     * @ORM\Id()
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * Тип события
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\EventType", inversedBy="events")
     * @ORM\JoinColumn(nullable=false)
     */
    private $eventType;

    /**
     * Клиент, отправивший событие
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Client", inversedBy="sendedEvents")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="client_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     * })
     */
    private $client;

    /**
     * @var string
     *
     * @ORM\Column(name="data", type="text", nullable=false, options={"comment"="Оригинальное событие"})
     */
    private $data;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"default"=0, "comment"="Количество ответов об удачно обработанном событии"})
     */
    private $successAnswerCount = 0;

    /**
     * @ORM\Column(type="datetime", options={"comment"="Дата создания"})
     */
    private $created_at;

    /**
     * Event constructor.
     */
    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
    }

    /**
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return EventType|null
     */
    public function getType(): ?EventType
    {
        return $this->eventType;
    }

    /**
     * @param EventType|null $eventType
     * @return Event
     */
    public function setType(?EventType $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return Client|null
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param Client|null $client
     * @return Event
     */
    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
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
     * @return int
     */
    public function getSuccessAnswerCount(): ?int
    {
        return $this->successAnswerCount;
    }

    /**
     * @param int $successAnswerCount
     * @return Event
     */
    public function setSuccessAnswerCount(int $successAnswerCount): self
    {
        $this->successAnswerCount = $successAnswerCount;

        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    /**
     * @param \DateTimeInterface $created_at
     * @return Event
     */
    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }
}
