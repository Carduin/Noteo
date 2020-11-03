<?php

namespace App\Repository;

use App\Entity\Points;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Points|null find($id, $lockMode = null, $lockVersion = null)
 * @method Points|null findOneBy(array $criteria, array $orderBy = null)
 * @method Points[]    findAll()
 * @method Points[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PointsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Points::class);
    }

    public function findByPartieAndByStudent($idPartie, $idEtudiant)
    {
        return $this->createQueryBuilder('n')
            ->join('n.partie', 'p')
            ->join('n.etudiant', 'e')
            ->andWhere('p.id = :idPartie')
            ->andWhere('e.id = :idEtudiant')
            ->setParameter('idPartie', $idPartie)
            ->setParameter('idEtudiant', $idEtudiant)
            ->getQuery()
            ->getSingleResult();
    }

    public function findAllByEvaluation($idEvaluation)
    {
        return $this->createQueryBuilder('n')
            ->join('n.partie', 'p')
            ->join('n.etudiant', 'et')
            ->join('p.evaluation', 'ev')
            ->andWhere('ev.id = :idEval')
            ->setParameter('idEval', $idEvaluation)
            ->addOrderBy('et.id', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNotesAndEtudiantByEvaluation($evaluation)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p, et, pa, ev
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN p.partie pa
            JOIN pa.evaluation ev
            WHERE ev = :param
            AND p.valeur >= 0
            AND pa.lvl = 0
            AND et.estDemissionaire = 0
        ')
            ->setParameter('param', $evaluation)
            ->execute();
    }

    /**
     * @return Points[] Returns an array of Points objects
     */

    public function findAllFromLowestParties($idEvaluation)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p
            FROM App\Entity\Points p
            JOIN p.partie pa
            JOIN pa.evaluation ev
            JOIN p.etudiant et
            WHERE ev.id = :idEvaluation
            AND pa.rgt = pa.lft + 1
            ORDER BY et.id ASC, pa.lft ASC
            ')
            ->setParameter('idEvaluation', $idEvaluation)
            ->execute();
    }

    public function findByGroupeAndPartie($idEval, $idGroupe, $idPartie)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p.valeur
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN et.groupes g
            JOIN p.partie pa
            JOIN pa.evaluation ev
            WHERE ev.id = :idE
            AND g.id = :idG
            AND pa.id = :idP
            AND et.estDemissionaire = 0
            AND p.valeur >= 0
            ORDER BY p.valeur ASC
        ')
            ->setParameter('idP', $idPartie)
            ->setParameter('idE', $idEval)
            ->setParameter('idG', $idGroupe)
            ->execute();
    }

    public function findAllNotesByGroupe($idEval, $idGroupe)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p.valeur
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN et.groupes g
            JOIN p.partie pa
            JOIN pa.evaluation ev
            WHERE ev.id = :idE
            AND g.id = :idG
            AND et.estDemissionaire = 0
            AND pa.lvl = 0
            AND p.valeur >= 0
            ORDER BY p.valeur DESC
        ')
            ->setParameter('idG', $idGroupe)
            ->setParameter('idE', $idEval)
            ->execute();
    }

    public function findAllNotesByStatut($idEval, $idStatut)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p.valeur
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN p.partie pa
            JOIN pa.evaluation ev
            JOIN et.statuts s
            WHERE s.id = :idStatut
            AND et.estDemissionaire = 0
            AND p.valeur >= 0
            AND ev.id = :idEval
            AND pa.lvl = 0
            ORDER BY p.valeur DESC
        ')
            ->setParameter('idEval', $idEval)
            ->setParameter('idStatut', $idStatut)
            ->execute();
    }

    public function findByStatutAndPartie($idEval, $idStatut, $idPartie)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p.valeur
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN et.statuts s
            JOIN p.partie pa
            JOIN pa.evaluation ev
            WHERE ev.id = :idE
            AND s.id = :idS
            AND pa.id = :idP
            AND et.estDemissionaire = 0
            AND p.valeur >= 0
            ORDER BY p.valeur ASC
        ')
            ->setParameter('idS', $idStatut)
            ->setParameter('idE', $idEval)
            ->setParameter('idP', $idPartie)
            ->execute();

    }

    public function findNoteByEvalAndStudent($idEval, $idEtudiant)
    {
        return $this->getEntityManager()->createQuery('
            SELECT p.valeur
            FROM App\Entity\Points p
            JOIN p.etudiant et
            JOIN p.partie pa
            JOIN pa.evaluation ev
            WHERE ev.id = :idEv
            AND et.id = :idEt
        ')
            ->setParameter('idEt', $idEtudiant)
            ->setParameter('idEv', $idEval)
            ->execute();

    }
}
