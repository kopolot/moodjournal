<?php

namespace App\Repository;

use App\Entity\MoodEntry;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

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
        $userId = $this->requireUserId($user);

        return $this->createQueryBuilder('m')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        $userId = $this->requireUserId($user);

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForUser(User $user, string $id): ?MoodEntry
    {
        $userId = $this->requireUserId($user);

        return $this->createQueryBuilder('m')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->andWhere('m.id = :id')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasEntryOnDate(User $user, \DateTimeImmutable $day): bool
    {
        $userId = $this->requireUserId($user);
        $start = $day->setTime(0, 0, 0);
        $end = $day->setTime(23, 59, 59);

        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->andWhere('m.createdAt BETWEEN :start AND :end')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function averageOverallForUser(User $user, int $days = 7): ?float
    {
        $userId = $this->requireUserId($user);
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        $result = $this->createQueryBuilder('m')
            ->select('AVG(m.overallMood)')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 2) : null;
    }

    /**
     * @return list<MoodEntry>
     */
    public function findBetween(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $userId = $this->requireUserId($user);

        return $this->createQueryBuilder('m')
            ->andWhere('IDENTITY(m.user) = :userId')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt < :to')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function requireUserId(User $user): Uuid
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \InvalidArgumentException('User must have an id.');
        }

        return $userId;
    }
}
