<?php

namespace App\Repository;

use App\Entity\QueryRoute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class QueryRouteRepository extends ServiceEntityRepository
{
    /**
     * QueryRouteRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, QueryRoute::class);
    }
}
