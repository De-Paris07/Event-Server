<?php

namespace App\Handler;

use App\Entity\Event;
use App\Job\EventJob;
use App\Repository\ClientRepository;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class EventHandler
 *
 * @package App\Handler
 */
class EventHandler
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var EventTypeRepository
     */
    private $eventTypeRepository;

    /**
     * @var ClientRepository
     */
    private $clientRepository;

    /**
     * EventHandler constructor.
     *
     * @param EntityManagerInterface $em
     * @param EventTypeRepository $eventTypeRepository
     * @param ClientRepository $clientRepository
     */
    public function __construct(
        EntityManagerInterface $em,
        EventTypeRepository $eventTypeRepository,
        ClientRepository $clientRepository
    ) {
        $this->em = $em;
        $this->eventTypeRepository = $eventTypeRepository;
        $this->clientRepository = $clientRepository;
    }

    /**
     * @param EventJob $job
     */
    public function checkEvent(EventJob $job)
    {
        if (!$job->getData()) {
            throw new NotFoundHttpException("Original data not found");
        }

        if (!$job->getClient()) {
            throw new NotFoundHttpException("Client not found");
        }
    }

    /**
     * Create event
     *
     * @param array $data
     *
     * @return Event
     *
     * @throws \Exception
     */
    public function create(array $data)
    {
        $event = new Event();

        $this->setData($event, $data);

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    /**
     * Update data of event
     *
     * @param Event $event Event for updating
     * @param array $data New data for event
     *
     * @return Event Updated event
     */
    public function update(Event &$event, array $data = [])
    {
        if (count($data)) {
            $this->setData($event, $data);
        }

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    /**
     * @param Event $event
     *
     * @return int
     */
    public function incrementSuccessAnswerCount(Event &$event)
    {
        $successAnswerCount = $event->getSuccessAnswerCount();
        $event->setSuccessAnswerCount(++$successAnswerCount);
        $this->update($event);

        return $successAnswerCount;
    }

    /**
     * Set data for event
     *
     * @param Event $event Event
     * @param array $data Data for event
     */
    private function setData(Event &$event, array $data)
    {
        $eventType = $data['eventType'];
        $client = $data['client'];
        $originalData = $data['originalData'];

        if (!$originalData) {
            throw new NotFoundHttpException("Original data not found");
        }

        if (!$eventType) {
            throw new NotFoundHttpException("Event type not found");
        }

        if (!$client) {
            throw new NotFoundHttpException("Client not found");
        }

        $event->setType($eventType);
        $event->setId($data['id']);
        $event->setClient($client);
        $event->setData($originalData);
    }
}
