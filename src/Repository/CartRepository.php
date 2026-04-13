<?php

namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Cart::class); }

    public function findAbandoned(int $minutes = 30): array
    {
        $limit = new \DateTimeImmutable("-{$minutes} minutes");

        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :s')->setParameter('s', Cart::STATUS_ACTIVE)
            ->andWhere('c.updatedAt <= :d')->setParameter('d', $limit)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function countAbandoned(int $minutes = 30): int
    {
        $limit = new \DateTimeImmutable("-{$minutes} minutes");

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :s')->setParameter('s', Cart::STATUS_ACTIVE)
            ->andWhere('c.updatedAt <= :d')->setParameter('d', $limit)
            ->getQuery()->getSingleScalarResult();
    }
}