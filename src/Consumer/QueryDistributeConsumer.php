<?php

namespace App\Consumer;

use App\Entity\QueryRoute;
use App\Exception\SendDataException;
use App\Job\Job;
use App\Job\QueryJob;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class QueryDistributeConsumer
 *
 * @package App\Consumer
 */
class QueryDistributeConsumer extends AbstractConsumer
{
    /**
     * @param QueryJob $job
     *
     * @throws \Exception
     */
    public function consume(Job $job)
    {
        $this->deliveryToServices = [];
        $this->deliveryTime = [];
        $this->startTime = (new \DateTime())->format('d-m-Y H:i:s.u');

        $this->checkEvent($job);

        $routeName = $job->getRoute();
        $clientName = null;

        if (stripos($routeName, '?') !== false) {
            $chanks = explode('?', $routeName);
            $routeName = $chanks[0];
            $clientName = $chanks[1] ?? null;
            unset($chanks);
        }

        $routeBuilder = $this->entityManager->getRepository(QueryRoute::class)
            ->createQueryBuilder('route')
            ->where('route.name = :routeName')
            ->setParameter('routeName', $routeName);

        if (!is_null($clientName)) {
            $routeBuilder->leftJoin('route.client', 'client')
                ->andWhere('client.name = :clientName')
                ->setParameter('clientName', $clientName);
        }

        $routes = $routeBuilder->getQuery()->getResult();

        if (empty($routes)) {
            $this->cacheService->setQueryResponse(
                $job->getEventId(), [
                'status' => 'error',
                'message' => !is_null($clientName) ? 'The client does not serve this route' : 'Rout not found',
                'code' => Response::HTTP_NOT_FOUND,
            ],
            30);

            return;
        }

        foreach ($routes as $route) {
            $this->entityManager->refresh($route);
            $this->deliveryToServices[] = $route->getClient()->getName();
            $this->sendDataToQueue($job, $route->getClient()->getDeliveryAddress() . '.query');
        }

        $this->buildLog($job);

        $this->entityManager->clear();
        unset($routes, $routeBuilder, $clientName, $routeName);
    }

    private function checkEvent(QueryJob $job)
    {
        if (!$job->getData()) {
            throw new NotFoundHttpException("Original data not found");
        }

        if (!$job->getClient()) {
            throw new NotFoundHttpException("Client not found");
        }
    }

    /**
     * @param QueryJob $job
     * @param string $tube
     *
     * @return int
     *
     * @throws SendDataException
     */
    private function sendDataToQueue(QueryJob $job, string $tube)
    {
        $exceptions = [];
        $data = $this->getData($job);

        for ($attempt = 0; $attempt < $_ENV['SEND_DATA_ATTEMPTS_COUNT']; $attempt++) {
            try {
                $queueJob = $this->pheanstalk->useTube($tube)->put(json_encode($data));
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
     * @param QueryJob $job
     *
     * @return array
     */
    private function getData(QueryJob $job)
    {
        return [
            'eventId' => $job->getEventId(),
            'event' => $job->getEventData(),
            'eventName' => $job->getEventName(),
            'serviceName' => $job->getServiceName(),
        ];
    }

    /**
     * @param QueryJob $job
     *
     * @throws \Exception
     */
    private function buildLog(QueryJob $job)
    {
        $data = $this->getData($job);
        $data['deliveryToServices'] = $this->deliveryToServices;
        $data['eventData'] = $data['event'];
        $data['eventServerProcessingTime'] = new \DateTime();
        $data['deliveryTime'] = json_encode(['start' => $this->startTime] + $this->deliveryTime, 128 | 256);
        unset($data['event']);
        unset($data['priority']);

        $this->logger->info('', $data);
        unset($data);
    }
}
