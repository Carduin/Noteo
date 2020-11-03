<?php

namespace App\Repository;

use App\Entity\Partie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Partie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Partie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Partie[]    findAll()
 * @method Partie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partie::class);
    }

    public function findLowestPartiesByEvaluationIdWithGrades($evaluation)
    {
        return $this->createQueryBuilder('p')
            ->addSelect('n')
            ->join('p.evaluation', 'e')
            ->leftJoin('p.notes', 'n')
            ->where('e.id = :idEval')
            ->andWhere('p.rgt = p.lft + 1')
            ->setParameter('idEval', $evaluation)
            ->getQuery()
            ->getResult();
    }

    //Cette fonction renvoie toute les parties d'une évaluation par parties dont la note doit être calculée (la note des parties les plus basses est déjà connue
    public function findHighestByEvaluation($idEvaluation)
    {
        return $this->createQueryBuilder('p')
            ->join('p.evaluation', 'e')
            ->where('e.id = :idEval')
            ->andWhere('p.rgt != p.lft + 1')
            ->orderBy('p.rgt')
            ->setParameter('idEval', $idEvaluation)
            ->getQuery()
            ->getResult();
    }
}
