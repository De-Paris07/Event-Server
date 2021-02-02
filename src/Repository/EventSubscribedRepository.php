<?php

namespace App\Repository;

use App\Entity\EventsSubscribed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class EventSubscribedRepository extends ServiceEntityRepository
{
    /**
     * EventSubscribedRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, EventsSubscribed::class);
    }
}
