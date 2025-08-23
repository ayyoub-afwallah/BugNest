<?php

namespace App\Infrastructure\Adapter\Persistence\Doctrine\Repository;

use App\Domain\Entity\Bug;
use App\Domain\Port\BugRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bug>
 */
class BugRepository extends ServiceEntityRepository implements BugRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bug::class);
    }

    public function create(Bug $bug): Bug
    {
        $this->getEntityManager()->persist($bug);
        $this->getEntityManager()->flush();

        return $bug;
    }
}
