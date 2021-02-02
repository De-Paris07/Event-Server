<?php

namespace App\Consumer;

use App\Entity\UnhandledEventsClient;
use App\Exception\SendDataException;
use App\Job\FailEventProcessingJob;
use App\Job\Job;

/**
 * Class FailEventProcessingConsumer
 *
 * @package App\Consumer
 */
class FailEventProcessingConsumer extends AbstractConsumer
{
    /**
     * @param FailEventProcessingJob $job
     *
     * @throws SendDataException
     */
    public function consume(Job $job)
    {
        $this->deliveryToServices = [];
        $this->deliveryTime = [];
        $this->clientPriority = [];
        $this->startTime = (new \DateTime())->format('d-m-Y H:i:s.u');
        $count = 0;

        do {
            if (!is_null($job->getEvent())) {
                break;
            }

            ++$count;
            sleep(1);
        } while ($count < 10);

        if (is_null($job->getClient()) || is_null($job->getEvent())) {
            return;
        }

        $this->entityManager->refresh($job->getClient());
        $this->entityManager->refresh($job->getEvent());
        $unhandledEvent = $job->getUnhandledEvent() ?? $this->createUnhandledEvent($job);

        if (is_null($subscribe = $job->getSubscribe()) || !$subscribe->isRetry()) {
            $this->entityManager->flush();
            $this->entityManager->clear();

            return;
        }

        if ($unhandledEvent->getCountRetry() >= $subscribe->getCountRetry()) {
            $this->entityManager->flush();
            $this->entityManager->clear();

            return;
        }

        $this->deliveryToServices[] = $job->getClient()->getName();

        $this->sendDataToQueue(
            $job->getEvent()->getData(),
            $job->getEvent()->getClient()->getDeliveryAddress(),
            $subscribe->getPriorityRetry(),
            $subscribe->getIntervalRetry()
        );

        $unhandledEvent->setCountRetry($unhandledEvent->getCountRetry() + 1);
        $this->buildLog($job, $unhandledEvent->getCountRetry());
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param string $data
     * @param string $tube
     * @param int $priority
     * @param int $delay
     *
     * @return int
     *
     * @throws SendDataException
     */
    private function sendDataToQueue(string $data, string $tube, int $priority, int $delay)
    {
        $exceptions = [];
        $this->clientPriority[$tube] = $priority;

        for ($attempt = 0; $attempt < $_ENV['SEND_DATA_ATTEMPTS_COUNT']; $attempt++) {
            try {
                $queueJob = $this->pheanstalk->useTube($tube)->put($data, $priority, $delay);
                $this->deliveryTime[$tube] = (new \DateTime())->format('d-m-Y H:i:s.u');

                return $queueJob;
            } catch (\Exception $e) {
                $exceptions[] = $e;
                continue;
            }
        }

        throw new SendDataException($exceptions);
    }

    /**
     * @param FailEventProcessingJob $job
     * @param int | null $countRetry
     * @param string | null $error
     */
    private function buildLog(FailEventProcessingJob $job, int $countRetry = null, string $error = null)
    {
        $data = [
            'eventId' => $job->getEventId(),
            'lastEventServerProcessingTime' => new \DateTime(),
            'lastDeliveryTime' => json_encode(['start' => $this->startTime] + $this->deliveryTime, 128 | 256),
            'lastDeliveryToServices' => $this->deliveryToServices,
        ];

        if (!is_null($countRetry)) {
            $data['countRetry'] = $countRetry;
        }

        $this->logger->info('', $data);
    }

    /**
     * @param FailEventProcessingJob $job
     *
     * @return UnhandledEventsClient
     */
    private function createUnhandledEvent(FailEventProcessingJob $job): UnhandledEventsClient
    {
        $unhandledEvent = new UnhandledEventsClient();
        $unhandledEvent->setClient($job->getClient())
            ->setEvent($job->getEvent());

        $this->entityManager->persist($unhandledEvent);

        return $unhandledEvent;
    }
}
