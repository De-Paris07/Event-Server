<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="event_type", uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 * @ORM\Entity(repositoryClass="App\Repository\EventTypeRepository")
 */
class EventType
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, options={"comment"="Наименование"})
     */
    private $name;

    /**
     * Клиенты, подписавшиеся на данный тип события
     *
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\EventsSubscribed", mappedBy="eventType")
     */
    private $subscribedClients;

    /**
     * Зарегистрированные события данного типа
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Event", mappedBy="eventType")
     */
    private $events;

    /**
     * EventType constructor.
     */
    public function __construct()
    {
        $this->subscribedClients = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return EventType
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return ArrayCollection | EventsSubscribed[]
     */
    public function getSubscribedClients()
    {
        return $this->subscribedClients;
    }

    /**
     * @param Client $client
     *
     * @return $this
     */
    public function subscribeClient(Client $client)
    {
        if ($this->getSubscribedClients()->contains($client)) {
            return $this;
        }

        $this->getSubscribedClients()->add($client);
        $client->subscribeToEventType($this);

        return $this;
    }

    /**
     * @param Client $client
     *
     * @return $this
     */
    public function unsubscribeClient(Client $client)
    {
        if (!$this->getSubscribedClients()->contains($client)) {
            return $this;
        }

        $this->getSubscribedClients()->removeElement($client);
        $client->unsubscribeFromEventType($this);

        return $this;
    }

    /**
     * @return Collection|Event[]
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * @param Event $event
     * @return EventType
     */
    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events[] = $event;
            $event->setEventType($this);
        }

        return $this;
    }

    /**
     * @param Event $event
     * @return EventType
     */
    public function removeEvent(Event $event): self
    {
        if ($this->events->contains($event)) {
            $this->events->removeElement($event);
            // set the owning side to null (unless already changed)
            if ($event->getType() === $this) {
                $event->setType(null);
            }
        }

        return $this;
    }
}
