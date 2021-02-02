<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="client", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name", columns={"name"}),
 *     @ORM\UniqueConstraint(name="client_server_token", columns={"client_token", "server_token"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\ClientRepository")
 */
class Client
{
    const DELIVERY_TYPE_REST = 1;
    const DELIVERY_TYPE_SOCKET = 2;
    const DELIVERY_TYPE_QUEUE = 3;

    const DELIVERY_TYPES = [
        self::DELIVERY_TYPE_REST => 'rest',
        self::DELIVERY_TYPE_SOCKET => 'socket',
        self::DELIVERY_TYPE_QUEUE => 'queue',
    ];

    /**
     * @var string
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, options={"comment"="Наименование"})
     */
    private $name;

    /**
     * @var integer
     * @ORM\Column(type="integer", options={"comment"="Метод доставки события"})
     */
    private $deliveryType;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, options={"comment"="Адресс доставки события"})
     */
    private $deliveryAddress;

    /**
     * @var boolean
     *
     * @ORM\Column(name="receive_historical_data", type="boolean", options={"comment"="нужно ли клиенту отправлять эвенты, помеченные как исторические"})
     */
    private $receiveHistoricalData;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, options={"comment"="Адресс уведомления об удачном выполнении события сервисами"})
     */
    private $successAddress;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, options={"comment"="Адресс уведомления о неудачном выполнении события сервисами"})
     */
    private $failAddress;

    /**
     * Типы событий, на которые подисан клиент
     *
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\EventsSubscribed", mappedBy="client")
     */
    private $eventTypes;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", length=6, options={"comment"="Дата создания"})
     */
    private $createdAt;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true, options={"comment"="Токен для аутентификации сервера"})
     */
    private $clientToken;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true, options={"comment"="Токен для аутентификации клиента"})
     */
    private $serverToken;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"comment"="Адрес сокета для эвентов и ответов на запросы"})
     */
    private $socketUri;

    /**
     * События, которые отправил клиент
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Event", mappedBy="client")
     */
    private $sendedEvents;

    /**
     * Роуты, которые может обработать клиент
     *
     * @ORM\OneToMany(targetEntity="App\Entity\QueryRoute", mappedBy="client")
     */
    private $routes;

    /**
     * События с ошибками
     *
     * @var UnhandledEventsClient[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\UnhandledEventsClient", mappedBy="client")
     */
    private $unhandledEvents;

    /**
     * @var Event | null
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Event")
     * @ORM\JoinColumn(name="last_event_id", referencedColumnName="id")
     */
    private $lastEvent;

    /**
     * Client constructor.
     */
    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
        $this->eventTypes = new ArrayCollection();
        $this->sendedEvents = new ArrayCollection();
        $this->routes = new ArrayCollection();
        $this->unhandledEvents = new ArrayCollection();
    }

    /**
     * @return null|string
     */
    public function getId(): ?string
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
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return int|null
     */
    public function getDeliveryType()
    {
        return $this->deliveryType;
    }

    /**
     * @param integer $deliveryType
     */
    public function setDeliveryType($deliveryType)
    {
        $this->deliveryType = $deliveryType;
    }

    /**
     * @param string $name
     * @return integer
     */
    static function getDeliveryTypeByName($name)
    {
        $deliveryTypes = self::DELIVERY_TYPES;
        $deliveryTypes = array_flip($deliveryTypes);

        return $deliveryTypes[$name];
    }

    /**
     * @return null|string
     */
    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    /**
     * @param string $deliveryAddress
     */
    public function setDeliveryAddress($deliveryAddress)
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    /**
     * @return bool
     */
    public function isReceiveHistoricalData(): bool
    {
        return $this->receiveHistoricalData;
    }

    /**
     * @param bool $receiveHistoricalData
     */
    public function setReceiveHistoricalData(bool $receiveHistoricalData): void
    {
        $this->receiveHistoricalData = $receiveHistoricalData;
    }

    /**
     * @return string
     */
    public function getSuccessAddress()
    {
        return $this->successAddress;
    }

    /**
     * @param string $address
     */
    public function setSuccessAddress($address)
    {
        $this->successAddress = $address;
    }

    /**
     * @return string
     */
    public function getFailAddress()
    {
        return $this->failAddress;
    }

    /**
     * @param string $address
     */
    public function setFailAddress($address)
    {
        $this->failAddress = $address;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return ArrayCollection | EventsSubscribed[]
     */
    public function getEventTypes()
    {
        return $this->eventTypes;
    }

    /**
     * @param EventType $eventType
     *
     * @return $this
     */
    public function subscribeToEventType(EventType $eventType)
    {
        if ($this->getEventTypes()->contains($eventType)) {
            return $this;
        }

        $this->getEventTypes()->add($eventType);
        $eventType->subscribeClient($this);

        return $this;
    }

    /**
     * @param EventType $eventType
     *
     * @return $this
     */
    public function unsubscribeFromEventType(EventType $eventType)
    {
        if (!$this->getEventTypes()->contains($eventType)) {
            return $this;
        }

        $this->getEventTypes()->removeElement($eventType);
        $eventType->unsubscribeClient($this);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getClientToken(): ?string
    {
        return $this->clientToken;
    }

    /**
     * @param string $clientToken
     */
    public function setClientToken($clientToken)
    {
        $this->clientToken = $clientToken;
    }

    /**
     * @return string|null
     */
    public function getServerToken(): ?string
    {
        return $this->serverToken;
    }

    /**
     * @param string $serverToken
     */
    public function setServerToken($serverToken)
    {
        $this->serverToken = $serverToken;
    }

    /**
     * @return Collection|Event[]
     */
    public function getSendedEvents(): Collection
    {
        return $this->sendedEvents;
    }

    /**
     * @param Event $event
     * @return Client
     */
    public function addSendedEvent(Event $event): self
    {
        if (!$this->sendedEvents->contains($event)) {
            $this->sendedEvents[] = $event;
            $event->setClient($this);
        }

        return $this;
    }

    /**
     * @param Event $event
     * @return Client
     */
    public function removeSendedEvent(Event $event): self
    {
        if ($this->sendedEvents->contains($event)) {
            $this->sendedEvents->removeElement($event);
            // set the owning side to null (unless already changed)
            if ($event->getClient() === $this) {
                $event->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /**
     * @param QueryRoute $route
     *
     * @return $this
     */
    public function addRoute(QueryRoute $route)
    {
        if (!$this->routes->contains($route)) {
            $this->routes->add($route);
            $route->setClient($this);
        }

        return $this;
    }

    /**
     * @param QueryRoute $route
     *
     * @return $this
     */
    public function removeRoute(QueryRoute $route)
    {
        if ($this->routes->contains($route)) {
            $this->routes->removeElement($route);

            if ($route->getClient() === $this) {
                $route->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @param Collection $routes
     *
     * @return $this
     */
    public function setRoutes(Collection $routes): self
    {
        foreach ($routes as $route) {
            $this->addRoute($route);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getSocketUri(): string
    {
        return $this->socketUri;
    }

    /**
     * @param string $socketUri
     */
    public function setSocketUri(string $socketUri): void
    {
        $this->socketUri = $socketUri;
    }

    /**
     * @return Event
     */
    public function getLastEvent(): ?Event
    {
        return $this->lastEvent;
    }

    /**
     * @param Event | null $lastEvent
     *
     * @return Client
     */
    public function setLastEvent(?Event $lastEvent): Client
    {
        $this->lastEvent = $lastEvent;

        return $this;
    }

    /**
     * @return UnhandledEventsClient[]
     */
    public function getUnhandledEvents(): Collection
    {
        return $this->unhandledEvents;
    }

    /**
     * @param UnhandledEventsClient[] $unhandledEvents
     * @return Client
     */
    public function setUnhandledEvents($unhandledEvents): Client
    {
        $this->unhandledEvents = $unhandledEvents;

        return $this;
    }
}
