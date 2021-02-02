<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Formatter\LogstashFormatter;
use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AbstractConsumer
 *
 * @package App\Consumer
 */
abstract class AbstractConsumer implements Consumer
{
    /**
     * @var Pheanstalk $pheanstalk
     */
    protected $pheanstalk;

    /** @var LoggerInterface $logger */
    protected $logger;

    /** @var EntityManagerInterface $entityManager */
    protected $entityManager;

    /** @var array $deliveryToServices */
    protected $deliveryToServices;

    /** @var array $deliveryTime */
    protected $deliveryTime = [];

    /** @var array $clientPriority */
    protected $clientPriority = [];

    /** @var string $startTime */
    protected $startTime;

    /** @var CacheService $cacheService */
    protected $cacheService;

    /**
     * AbstractConsumer constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param CacheService $cacheService
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface  $entityManager,
        CacheService $cacheService
    ) {
        $this->pheanstalk = $container->get("leezy.pheanstalk");
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        $this->logger = $container->get('monolog.logger.customEvent');
        $handler = $this->logger->getHandlers()[0];
        $handler->setFormatter(new LogstashFormatter('eventServer', null, null, '', 1));
    }
}
