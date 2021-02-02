<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UnhandledEventsClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class UnhandledEventsClientRepository extends ServiceEntityRepository
{
    /**
     * UnhandledEventsClientRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UnhandledEventsClient::class);
    }
}
