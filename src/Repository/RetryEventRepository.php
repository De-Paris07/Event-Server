<?php

namespace App\Repository;

use App\Entity\RetryEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class RetryEventRepository extends ServiceEntityRepository
{
    /**
     * RetryEventRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, RetryEvent::class);
    }
}
