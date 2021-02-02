<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\QueryRoute;
use App\Exception\ConnectTimeoutException;
use App\Loop\LoopTrait;
use App\Loop\SocketMessage;
use App\Service\TelegramLogger;
use App\Socket\SocketClient;
use App\Socket\SocketMessageInterface;
use App\Socket\SocketServer;
use Doctrine\ORM\EntityManagerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EventAndQuerySocketCommand
 *
 * @package App\Command
 */
class EventAndQuerySocketCommand extends Command
{
    use LoopTrait;

    protected static $defaultName = 'event:server:socket';

    /** @var ContainerInterface $container */
    private $container;

    /** @var array<ConnectionInterface> $connections */
    private $callbacks;

    /** @var SocketServer $tcpServer */
    private $tcpServer;

    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;

    /** @var array $servicesInfo */
    private $servicesInfo = [];

    /**
     * EventAndQuerySocketCommand constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param TelegramLogger $telegramLogger
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        TelegramLogger $telegramLogger
    ) {
        parent::__construct();
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->telegramLogger = $telegramLogger;
        $this->tcpServer = new SocketServer($this->getSocketUri());
    }

    protected function configure()
    {
        $this->setDescription('Сокет для общение с эвент-сервером');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initClient();

        try {
            $this->tcpServer
                ->setLoop($this->loop)
                ->connect();
        } catch (\Throwable $exception) {
            $this->telegramLogger->setFail($exception);
        }

        $handler = function ($timer) use ($output) {
            $test = 1;
        };

        $this->handleEvent();
        $this->addJob($handler);
        $this->start();
    }

    private function handleEvent()
    {
        $this->getLoadRoutes();
        $this->getListServices();
        $this->healthCheckerService();
        $this->queryDispatch();
    }

    private function getLoadRoutes()
    {
        $this->tcpServer->on(SocketServer::SOCKET_LOAD_ROUTES_CHANNEL, function (SocketMessage $message) {
            echo 'Пришел запрос - ' . (string) $message . PHP_EOL;
            $response = [
                'success' => false,
                'data' => null,
            ];

            $routes = $this->entityManager->getRepository(QueryRoute::class)
                ->createQueryBuilder('route')
                ->leftJoin('route.client', 'client')
                ->orderBy('client.name')
                ->getQuery()
                ->getResult();

            $response['success'] = true;

            foreach ($routes as $route) {
                $response['data'][] = [
                    'name' => $route->getName(),
                    'description' => $route->getDescription(),
                    'client' => $route->getClient()->getName(),
                    'validateSchema' => null,
                ];
            }

            $responseMessage = new SocketMessage($message->getChannel(), $response, $message->getXid());
            $this->tcpServer
                ->setWaitForAnAnswer(false)
                ->write($responseMessage);

            echo 'Отправили ответ - ' . (string) $responseMessage . PHP_EOL;

            $this->entityManager->clear();
            unset($responseMessage, $routes, $response);
        });
    }

    private function getListServices()
    {
        $this->tcpServer->on(SocketServer::SOCKET_SERVICES_LIST, function (SocketMessage $message) {
            $services = $this->entityManager->getRepository(Client::class)
                ->createQueryBuilder('client')
                ->select('client.name, client.socketUri')
                ->where('client.socketUri IS NOT NULL')
                ->getQuery()
                ->getArrayResult();

            $this->tcpServer
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage(
                    $message->getChannel(),
                    ['services' => $services],
                    $message->getXid()
                ));

            unset($services);
        });
    }

    private function healthCheckerService()
    {
        $errorHandler = function (SocketMessage &$message, string $error) {
            $this->tcpServer
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage(
                   $message->getChannel(),
                   ['error' => $error],
                   $message->getXid()
                ));
        };

        // метод для отдачи состояния любого сервиса
        $this->tcpServer->on(SocketServer::SOCKET_HEALTH_CHECK, function (SocketMessage $message) use ($errorHandler) {
            echo 'Пришел запрос - ' . (string) $message . PHP_EOL;
            $serviceName = $message->getField('serviceName');

            if (is_null($serviceName)) {
                $errorHandler($message, 'Not found parameter "serviceName"');
                return;
            }

            /** @var Client $client */
            $client = $this->entityManager->getRepository(Client::class)
                ->findOneBy(['name' => $serviceName]);

            if (is_null($client)) {
                $errorHandler($message, "Not found service of name - '$serviceName'");
                return;
            }

            try {
                // обращаемся к конечному сервису
                $socket = new SocketClient();
                $socket->on(SocketClient::SOCKET_CONNECTED_ERROR_CHANNEL, function (string $error) use ($errorHandler, $message, $client) {
                    $errorHandler($message, 'The service is not running');

                    echo "Сервис '{$client->getName()}' - '{$client->getSocketUri()}' не запущен " . PHP_EOL;
                    unset($message);
                });
                $socket
                    ->setDebug(false)
                    ->setUri($client->getSocketUri())
                    ->setWaitForAnAnswer(true)
                    ->setIsReconnect(false)
                    ->setTimeoutConnect(1)
                    ->setTimeoutSocketWrite(1)
                    ->writeWithoutListening(
                        new SocketMessage($message->getChannel(), []),
                        function (SocketMessageInterface $response) use ($message, $client, $socket) {
                            $this->tcpServer
                                ->setConnection($message->getConnection())
                                ->setWaitForAnAnswer(false)
                                ->write(new SocketMessage(
                                    $message->getChannel(),
                                    $response->getData(),
                                    $message->getXid()
                                ));

                            $socket->removeAllListeners('connected.error');
                            echo "Отправили ответ на запрос состояния сервиса - '{$client->getName()}' - '{$client->getSocketUri()}'" . PHP_EOL;
                            unset($message, $client, $socket, $response);
                        },
                        function () use ($errorHandler, $message, $socket, $client) {
                            $errorHandler($message, 'Service did not respond');
                            $socket->removeAllListeners('connected.error');
                            unset($message, $socket);
                            echo "Сервис '{$client->getName()}' - '{$client->getSocketUri()}' не ответил на запрос своего здоровья " . PHP_EOL;
                        },
                        true
                    );
            } catch (ConnectTimeoutException $timeoutException) {
                $errorHandler($message, 'The service is not running');

                if (isset($socket)) {
                    $socket->removeAllListeners('connected.error');
                }

                echo "Сервис '{$client->getName()}' - '{$client->getSocketUri()}' не запущен " . PHP_EOL;

                unset($timeoutException, $message);
            }

            unset($client, $socket, $serviceName, $message);
        });

        // метод для получения состояния сервиса
        $this->tcpServer->on(SocketServer::SOCKET_CHANNEL_HEALTH_CHECK_DATA, function (SocketMessage $message) {
            $this->servicesInfo[$message->getField('serviceName')] = [
                'memory' => $message->getField('memory'),
                'cpu' => $message->getField('cpu'),
                'conn' => $message->getConnection(),
            ];
            echo json_encode($message->getData()) . ' ' . PHP_EOL;
            echo $message->getConnection()->getRemoteAddress() . PHP_EOL;
        });

        $this->tcpServer->on(SocketServer::SOCKET_DISCONNECT_CLIENT_CHANNEL, function (ConnectionInterface $connection) {
            $address = $connection->getRemoteAddress();

            foreach ($this->servicesInfo as $name => $service) {
                if ($service['conn']->getRemoteAddress() === $address) {
                    unset($this->servicesInfo[$name]);
                    echo "Удалили сервис - $name " . PHP_EOL;

                    break;
                }
            }

            unset($address, $connection);
        });
    }

    /**
     * Получение метрик сервисов для определения на какую ноду отправить запрос
     */
    private function queryDispatch()
    {
        $this->tcpServer->on(SocketClient::SOCKET_QUERY_DISPATCH, function (SocketMessage $request) {
//            $services = $this->entityManager->getRepository(Client::class)
//                ->createQueryBuilder('client')
//                ->select('client.name, client.socketUri')
//                ->where('client.socketUri IS NOT NULL')
//                ->getQuery()
//                ->getArrayResult();
        });
    }

    /**
     * @return string
     */
    private function getSocketUri(): string
    {
        return $_ENV['CURRENT_HOST'] . ':' . $_ENV['SOCKET_PORT'];
    }
}
