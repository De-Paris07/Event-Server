<?php

namespace App\Job;

use App\Entity\EventsSubscribed;
use App\Entity\UnhandledEventsClient;

/**
 * Class AnswerJob
 *
 * @package App\Job
 */
class AnswerJob extends BaseJob
{

    /**
     * @return EventsSubscribed | null
     */
    public function getSubscribe(): ?EventsSubscribed
    {
        if (is_null($client = $this->getClient())) {
            return null;
        }

        return $this->getEventSubscribedRepository()->findOneBy([
            'client' => $client,
            'eventType' => $this->getEvent()->getType(),
        ]);
    }

    /**
     * @return UnhandledEventsClient | null
     */
    public function getUnhandledEvent(): ?UnhandledEventsClient
    {
        if (is_null($client = $this->getClient())) {
            return null;
        }

        return $this->getUnhandledEventsClientRepository()->findOneBy([
            'client' => $client,
            'event' => $this->getEvent(),
        ]);
    }
}
