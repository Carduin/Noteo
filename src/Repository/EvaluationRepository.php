<?php

namespace App\Repository;

use App\Entity\Evaluation;
use App\Entity\Points;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Evaluation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evaluation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evaluation[]    findAll()
 * @method Evaluation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

    /**
     * @return Evaluation[] Returns an array of Evaluation objects
     */

    public function findOtherEvaluationsWithGradesAndCreatorAndGroup($enseignant)
    {
        return $this->createQueryBuilder('e')
            ->addSelect('p')
            ->addSelect('en')
            ->addSelect('g')
            ->addSelect('n')
            ->leftJoin('e.parties', 'p')
            ->leftJoin('p.notes', 'n')
            ->join('e.groupe', 'g')
            ->join('e.enseignant', 'en')
            ->andWhere('e.enseignant != :enseignant')
            ->andWhere('n.valeur >= 0')
            ->setParameter('enseignant', $enseignant)
            ->getQuery()
            ->getResult();
    }

    public function findMyEvaluationsWithGradesAndCreatorAndGroup($enseignant)
    {
        return $this->createQueryBuilder('e')
            ->addSelect('p')
            ->addSelect('en')
            ->addSelect('g')
            ->addSelect('n')
            ->leftJoin('e.parties', 'p')
            ->leftJoin('p.notes', 'n')
            ->join('e.groupe', 'g')
            ->join('e.enseignant', 'en')
            ->andWhere('e.enseignant = :enseignant')
            ->andWhere('n.valeur >= 0')
            ->setParameter('enseignant', $enseignant)
            ->getQuery()
            ->getResult();
    }

    public function findAllWithOnePart()
    {
        return $this->getEntityManager()->createQuery('
            SELECT e, g, en
            FROM App\Entity\Evaluation e
            JOIN e.groupe g
            JOIN e.enseignant en
            JOIN e.parties pa
            GROUP BY e,g, en
            HAVING count(pa.id) = 1
        ')
            ->execute();
    }

    public function findAllWithSeveralParts()
    {
        return $this->getEntityManager()->createQuery('
            SELECT e, g, en
            FROM App\Entity\Evaluation e
            JOIN e.groupe g
            JOIN e.enseignant en
            JOIN e.parties pa
            GROUP BY e,g, en
            HAVING count(pa.id) > 1
        ')
            ->execute();
    }

    public function findAllByStatut($idStatut)
    {
        return $this->getEntityManager()->createQuery('
            SELECT e
            FROM App\Entity\Evaluation e
            JOIN e.groupe g
            JOIN g.etudiants et
            JOIN et.statuts s
            WHERE s.id = :idStatut
        ')
            ->setParameter('idStatut', $idStatut)
            ->execute();
    }

    public function findAllByEtudiant($idEtudiant)
    {
        return $this->getEntityManager()->createQuery('
            SELECT e
            FROM App\Entity\Evaluation e
            JOIN e.groupe g
            JOIN g.etudiants et
            JOIN e.parties p
            JOIN p.notes n
            WHERE et.id = :idEtudiant
            AND p.lvl = 0
            AND n.valeur != -1
            AND n.etudiant = et
            ORDER BY e.date
        ')
            ->setParameter('idEtudiant', $idEtudiant)
            ->execute();
    }


    public function findAllOverAGroupExceptCurrentOne($idGroupe, $idEvalCourante)
    {
        return $this->getEntityManager()->createQuery('
            SELECT e
            FROM App\Entity\Evaluation e
            JOIN e.groupe g
            JOIN g.enseignant et
            WHERE g.id = :idG
            AND e.id != :idE
            ')
            ->setParameter('idG', $idGroupe)
            ->setParameter('idE', $idEvalCourante)
            ->execute();
    }
}
