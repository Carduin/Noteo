<?php

namespace App\Repository;

use App\Entity\ApiLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ApiLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiLog[]    findAll()
 * @method ApiLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiLog::class);
    }

    public function getAllLogsWithEnseignants() {
        return $this->createQueryBuilder('l')
            ->addSelect('e')
            ->join('l.enseignant', 'e')
            ->orderBy('l.calledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
