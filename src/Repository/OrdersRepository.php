<?php

namespace App\Repository;

use App\Entity\Orders;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orders::class);
    }

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['email'])) {
            $qb->andWhere('o.email LIKE :email')->setParameter('email', '%' . $filters['email'] . '%');
        }
        if (!empty($filters['number'])) {
            $qb->andWhere('o.orderNumber LIKE :number')->setParameter('number', '%' . $filters['number'] . '%');
        }
        if (!empty($filters['from'])) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', new \DateTime($filters['from']));
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', new \DateTime($filters['to'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    public function countSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->andWhere('o.status != :cancelled')
            ->setParameter('cancelled', Orders::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', [Orders::STATUS_NEW, Orders::STATUS_PREPARING])
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findClientsGrouped(?string $emailFilter, ?string $nameFilter): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select(
                'o.email',
                'o.firstName',
                'o.lastName',
                'o.phone',
                'COUNT(o.id) AS orderCount',
                'SUM(o.total) AS totalSpent',
                'MAX(o.createdAt) AS lastOrder'
            )
            ->groupBy('o.email, o.firstName, o.lastName, o.phone')
            ->orderBy('lastOrder', 'DESC');

        if ($emailFilter) {
            $qb->andWhere('o.email LIKE :email')->setParameter('email', '%' . $emailFilter . '%');
        }
        if ($nameFilter) {
            $qb->andWhere('o.firstName LIKE :name OR o.lastName LIKE :name')
               ->setParameter('name', '%' . $nameFilter . '%');
        }

        return $qb->getQuery()->getResult();
    }
    public function countByStatus(string $status): int
{
    return (int) $this->createQueryBuilder('o')
        ->select('COUNT(o.id)')
        ->andWhere('o.status = :s')->setParameter('s', $status)
        ->getQuery()->getSingleScalarResult();
}

public function countCreatedSince(\DateTimeImmutable $since): int
{
    return (int) $this->createQueryBuilder('o')
        ->select('COUNT(o.id)')
        ->andWhere('o.createdAt >= :d')->setParameter('d', $since)
        ->getQuery()->getSingleScalarResult();
}
}
