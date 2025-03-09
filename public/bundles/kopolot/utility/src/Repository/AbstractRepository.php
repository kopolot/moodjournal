<?php

namespace Kopolot\Utility\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class AbstractRepository extends ServiceEntityRepository{

    public function save(object $entity): void
    {
        $this->validateEntityType($entity);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function delete(object $entity): void
    {
        $this->validateEntityType($entity);
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    private function validateEntityType(object $entity): void
    {
        if (!is_a($entity, $this->getClassName())) {
            throw new \InvalidArgumentException('Invalid entity type');
        }
    }
}