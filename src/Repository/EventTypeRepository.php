<?php

namespace App\Repository;

use App\Entity\EventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method EventType|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventType|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventType[]    findAll()
 * @method EventType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventTypeRepository extends ServiceEntityRepository
{
    /**
     * EventTypeRepository constructor.
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, EventType::class);
    }
}
