<?php

namespace App\Handler;

use App\Entity\EventType;
use Doctrine\ORM\EntityManagerInterface;

class EventTypeHandler
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * ClientHandler constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Create event type
     *
     * @param array $data Data for creating event type
     * @return EventType Created event type
     */
    public function create(array $data)
    {
        $eventType = new EventType();
        $eventType->setName($data['name']);

        $this->em->persist($eventType);
        $this->em->flush();

        return $eventType;
    }
}
