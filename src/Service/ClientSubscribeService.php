<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\EventsSubscribed;
use App\Entity\QueryRoute;
use App\Entity\UnhandledEventsClient;
use App\Exception\ValidateFormException;
use App\Form\SubscribeType;
use App\Handler\ClientHandler;
use App\Handler\EventTypeHandler;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClientSubscribeService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ClientHandler
     */
    private $clientHandler;

    /**
     * @var EventTypeHandler
     */
    private $eventTypeHandler;

    /**
     * @var EventTypeRepository
     */
    private $eventTypeRepository;

    /** @var CacheService $cacheService */
    private $cacheService;

    /**
     * @var ClientAuthService
     */
    private $auth;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * ClientSubscribeService constructor.
     *
     * @param EntityManagerInterface $em
     * @param ClientHandler $clientHandler
     * @param EventTypeHandler $eventTypeHandler
     * @param EventTypeRepository $eventTypeRepository
     * @param ClientAuthService $auth
     * @param CacheService $cacheService
     * @param ContainerInterface $container
     */
    public function __construct(
        EntityManagerInterface $em,
        ClientHandler $clientHandler,
        EventTypeHandler $eventTypeHandler,
        EventTypeRepository $eventTypeRepository,
        ClientAuthService $auth,
        CacheService $cacheService,
        ContainerInterface $container
    ) {
        $this->em = $em;
        $this->clientHandler = $clientHandler;
        $this->eventTypeHandler = $eventTypeHandler;
        $this->eventTypeRepository = $eventTypeRepository;
        $this->auth = $auth;
        $this->cacheService = $cacheService;
        $this->container = $container;
    }

    /**
     * Create subscribing on events for client
     *
     * @param array $subscribingData
     * @return Client
     * @throws \Exception
     */
    public function createSubscription(array $subscribingData)
    {
        $this->validateData($subscribingData);

        $this->em->beginTransaction();

        try {
            $client = $this->clientHandler->create($subscribingData);
            $this->auth->setupAuthForClient($client);
            $this->subscribeClientOnEvents($client, $subscribingData['eventsSubscribe']);
            $this->handleRoutes($client, $subscribingData);

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $client;
    }

    /**
     * Update subscribing on events for client
     *
     * @param Client $client
     * @param array $subscribingData
     * @return Client
     * @throws \Exception
     */
    public function updateSubscription(Client $client, array $subscribingData)
    {
        $this->validateData($subscribingData);

        $this->em->beginTransaction();

        try {
            $this->clientHandler->update($client, $subscribingData);

            $requiredEvents = $subscribingData['eventsSubscribe'];
            $this->removeNotRequiredEventsForClient($client, $requiredEvents);
            $this->subscribeClientOnEvents($client, $requiredEvents);
            $this->handleRoutes($client, $subscribingData);

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $client;
    }

    /**
     * @param array $data
     */
    public function unsubscribe(array $data)
    {
        $client = $this->em->getRepository(Client::class)
            ->createQueryBuilder('client')
            ->where('client.name = :name OR client.successAddress = :successAddress')
            ->setParameters(['name' => $data['name'], 'successAddress' => $data['callbackSuccessUrl']])
            ->getQuery()
            ->getResult();

        /** @var Client $client */
        if (!$client = current($client)) {
            return;
        }

        $this->em->beginTransaction();

        try {
            if (!is_null($client->getLastEvent())) {
                $client->setLastEvent(null);
                $this->em->flush();
            }

            foreach ($client->getEventTypes() as $currentEvent) {
                $client->unsubscribeFromEventType($currentEvent->getEventType());
                $this->em->remove($currentEvent);
            }

            foreach ($client->getUnhandledEvents() as $unhandledEvent) {
                $this->em->remove($unhandledEvent);
            }

            foreach ($client->getSendedEvents() as $event) {
                $clientsLastEvent = $this->em->getRepository(Client::class)->findBy(['lastEvent' => $event]);

                if (!is_null($clientsLastEvent)) {
                    foreach ($clientsLastEvent as $client) {
                        $client->setLastEvent(null);
                    }

                    $this->em->flush();
                }

                $unhandledEvents = $this->em->getRepository(UnhandledEventsClient::class)
                    ->findBy(['event' => $event]);

                if (!is_null($unhandledEvents)) {
                    foreach ($unhandledEvents as $unhandledEvent) {
                        $this->em->remove($unhandledEvent);
                    }

                    $this->em->flush();
                }

                $this->em->remove($event);
            }

            foreach ($client->getRoutes() as $route) {
                $client->removeRoute($route);
                $this->em->remove($route);

                if ($this->em->getRepository(QueryRoute::class)->count(['name' => $route->getName()]) == 1) {
                    $this->cacheService->removeRoute($route->getName());
                }
            }

            $this->em->remove($client);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        }
    }

    /**
     * @param array $subscribingData
     */
    private function validateData(array &$subscribingData)
    {
        if (isset($subscribingData['eventsSubscribe'])) {
            foreach ($subscribingData['eventsSubscribe'] as &$subscribe) {
                if (!isset($subscribe['isRetry'])) {
                    $subscribe['isRetry'] = false;
                }

                if (!isset($subscribe['countRetry'])) {
                    $subscribe['countRetry'] = 0;
                }

                if (!isset($subscribe['intervalRetry'])) {
                    $subscribe['intervalRetry'] = 60;
                }

                if (!isset($subscribe['priorityRetry'])) {
                    $subscribe['priorityRetry'] = 1024;
                }
            }
        }

        $currentRoutes = $subscribingData['routes'];
        $routes = [];

        if (is_array($subscribingData['routes'])) {
            foreach ($subscribingData['routes'] as $route) {
                $routes[] = $route;
            }
        }

        $subscribingData['routes'] = $routes;

        $form = $this->container->get('form.factory')->create(SubscribeType::class, null, []);
        $form->submit($subscribingData);

        if (!$form->isValid()) {
            throw new ValidateFormException($form);
        }

        $subscribingData['routes'] = $currentRoutes;
    }

    /**
     * @param Client $client
     * @param array $events
     */
    private function subscribeClientOnEvents(Client &$client, array $events)
    {
        foreach ($events as $event) {
            $eventType = $this->eventTypeRepository->findOneBy(['name' => $event['name']]);

            if (!$eventType) {
                $eventType = $this->eventTypeHandler->create($event);
            }

            $eventSubscribe = $this->em->getRepository(EventsSubscribed::class)
                ->findOneBy(['client' => $client, 'eventType' => $eventType]);

            if (is_null($eventSubscribe)) {
                $eventSubscribe = new EventsSubscribed();
                $eventSubscribe->setClient($client)
                    ->setEventType($eventType);

                $this->em->persist($eventSubscribe);
            }

            $eventSubscribe->setPriority($event['priority']);
            $eventSubscribe->setChannel($event['channel']);
            $eventSubscribe->setServicePriority($event['servicePriority']);
            $eventSubscribe->setRetry($event['isRetry']);
            $eventSubscribe->setCountRetry($event['countRetry']);
            $eventSubscribe->setIntervalRetry($event['intervalRetry']);
            $eventSubscribe->setPriorityRetry($event['priorityRetry']);
        }

        $this->clientHandler->update($client);
    }

    /**
     * @param Client $client
     * @param array $requiredEvents
     */
    private function removeNotRequiredEventsForClient(Client &$client, array $requiredEvents)
    {
        $requiredEvents = array_map(function ($eventTypeData) {
                return $eventTypeData['name'];
            },
            $requiredEvents
        );

        foreach ($client->getEventTypes() as $currentEvent) {
            if (!in_array($currentEvent->getEventType()->getName(), $requiredEvents)) {
                $client->unsubscribeFromEventType($currentEvent->getEventType());
                $this->em->remove($currentEvent);
            }
        }

        $this->clientHandler->update($client);
    }

    /**
     * @param Client $client
     * @param array $subscribingData
     *
     * @throws \Exception
     */
    private function handleRoutes(Client $client, array $subscribingData)
    {
        $routes = $subscribingData['routes'] ?? null;

        if (is_null($routes)) {
            foreach ($client->getRoutes() as $route) {
                $client->removeRoute($route);
                $this->em->remove($route);

                if ($this->em->getRepository(QueryRoute::class)->count(['name' => $route->getName()]) == 1) {
                    $this->cacheService->removeRoute($route->getName());
                }
            }

            return;
        }

        $this->setRoutes($client, $routes);
        $this->em->refresh($client);

        /** @var QueryRoute $route */
        foreach ($client->getRoutes() as $route) {
            $this->cacheService->setRoute($route->getName(), [
                'validateSchema' => null,
                'route' => $route->getName(),
                'description' => $route->getDescription(),
                'address' => $client->getName(),
            ]);
        }
    }

    /**
     * @param Client $client
     * @param array | null $routes
     *
     * @throws \Exception
     */
    private function setRoutes(Client $client, array $routes = null)
    {
        if (is_null($routes) || empty($routes)) {
            return;
        }

        /** @var QueryRoute $route */
        foreach ($client->getRoutes() as $route) {
            if (key_exists($route->getName(), $routes)) {
                $route->setDescription($routes[$route->getName()]['description']);
                $this->cacheService->setRoute($route->getName(), [
                    'validateSchema' => null,
                    'route' => $route->getName(),
                    'description' => $route->getDescription(),
                    'address' => $client->getName(),
                ]);
                unset($routes[$route->getName()]);
                continue;
            }

            $client->removeRoute($route);
            $this->em->remove($route);

            if ($this->em->getRepository(QueryRoute::class)->count(['name' => $route->getName()]) == 1) {
                $this->cacheService->removeRoute($route->getName());
            }
        }

        foreach ($routes as $route) {
            $queryRoute = new QueryRoute();
            $queryRoute->setName($route['name']);
            $queryRoute->setDescription($route['description']);
            $queryRoute->setClient($client);
            $queryRoute->setCreatedAt(new \DateTime());

            $this->em->persist($queryRoute);
        }

        $this->em->flush();
    }
}
