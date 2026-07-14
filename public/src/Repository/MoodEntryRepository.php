<?php

namespace App\Repository;

use App\Entity\MoodEntry;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Kopolot\Utility\Repository\AbstractRepository;

/**
 * @extends AbstractRepository<MoodEntry>
 */
class MoodEntryRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoodEntry::class);
    }

    /**
     * @return list<MoodEntry>
     */
    public function findByUser(User $user, int $limit = 30, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForUser(User $user, string $id): ?MoodEntry
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasEntryOnDate(User $user, \DateTimeImmutable $day): bool
    {
        $start = $day->setTime(0, 0, 0);
        $end = $day->setTime(23, 59, 59);

        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.user = :user')
            ->andWhere('m.createdAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function averageOverallForUser(User $user, int $days = 7): ?float
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        $result = $this->createQueryBuilder('m')
            ->select('AVG(m.overallMood)')
            ->andWhere('m.user = :user')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 2) : null;
    }
}
