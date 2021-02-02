<?php

namespace App\Consumer;

use App\Handler\EventHandler;
use App\Job\Job;
use App\Job\SuccessEventProcessingJob;

/**
 * Class SuccessEventProcessingConsumer
 *
 * @package App\Consumer
 */
class SuccessEventProcessingConsumer extends AbstractConsumer
{
    /**
     * @var EventHandler
     */
    private $eventHandler;

    /**
     * @param SuccessEventProcessingJob $job
     *
     * @return void
     */
    public function consume(Job $job)
    {
        $event = $job->getEvent();

        if (is_null($event)) {
            return;
        }

        if (is_null($unhandledEvent = $job->getUnhandledEvent())) {
            return;
        }

        $this->entityManager->remove($unhandledEvent);
        $this->entityManager->flush();
        $this->entityManager->clear();

//        $this->eventHandler->incrementSuccessAnswerCount($event);

//        $eventType = $event->getType();

        /*
         * Дано:
         * Сервисы обработки событий запущены. Новый клиент регистрируется и подписывается на события.
         * В очередь приходит новое событие, на которое подписан клиент.
         *
         * Без нижеследующей строки количество подписанных на событие клиентов не учитывает нового клиента,
         * а будет равно клиентам регистрировавшимся и подписавшимся на событие до запуска сервисов обработки событий.
         * */
//        $this->em->refresh($eventType);

//        $subscribedClientCount = $eventType->getSubscribedClients()->count();
    }
}
