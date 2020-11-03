<?php

namespace App\Repository;

use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statut::class);
    }

    /**
     * @return Statut[] Returns an array of Statut objects
     */

    public function findAllHavingStudents()
    {
        return $this->createQueryBuilder('s')
            ->addSelect('e')
            ->join('s.etudiants', 'e')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Statut[] Returns an array of Statut objects
     */

    public function findAllWithStudents()
    {
        return $this->createQueryBuilder('s')
            ->addSelect('e')
            ->leftjoin('s.etudiants', 'e')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Statut[] Returns an array of Statut objects
     */

    public function findByEvaluation($idEval)
    {

        return $this->getEntityManager()->createQuery('
            SELECT s
            FROM App\Entity\Statut s
            JOIN s.etudiants et
            JOIN et.groupes ge
            JOIN ge.evaluations ev
            WHERE ev.id = :id
            ORDER BY s.id
        ')
            ->setParameter('id', $idEval)
            ->execute();
    }

    public function findAllWith1EvalOrMore()
    {

        return $this->getEntityManager()->createQuery('
            SELECT s
            FROM App\Entity\Statut s
            JOIN s.etudiants et
            JOIN et.groupes ge
            JOIN ge.evaluations ev
        ')
            ->execute();
    }
}
