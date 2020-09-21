<?php

namespace App\Repository;

use App\Entity\Etudiant;
use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Etudiant|null find($id, $lockMode = null, $lockVersion = null)
 * @method Etudiant|null findOneBy(array $criteria, array $orderBy = null)
 * @method Etudiant[]    findAll()
 * @method Etudiant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EtudiantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etudiant::class);
    }

    /**
     * @return Etudiant[] Returns an array of Etudiant objects
     */

    public function findAllFromGroupParentButNotCurrent($parent, $current)
    {
        return $this->getEntityManager()->createQuery('
        SELECT e
        FROM App\Entity\Etudiant e
        JOIN e.groupes g
        WHERE g.slug = :slugGroupeParent AND
        e.id NOT IN (SELECT e2.id FROM App\Entity\Etudiant e2 JOIN e2.groupes g2 WHERE g2.slug = :slugGroupeCourant)
        ')
            ->setParameter('slugGroupeParent', $parent->getSlug())
            ->setParameter('slugGroupeCourant', $current->getSlug())
            ->execute();
    }

    /**
     * @return Etudiant[] Returns an array of Etudiant objects
     */

    public function findAllButNotFromCurrentStatuts($current)
    {
        return $this->getEntityManager()->createQuery('
        SELECT e
        FROM App\Entity\Etudiant e
        LEFT JOIN e.statuts s
        WHERE e.id NOT IN (SELECT e2.id FROM App\Entity\Etudiant e2 JOIN e2.statuts s2 WHERE s2.slug = :slugStatutCourant)
        ')
            ->setParameter('slugStatutCourant', $current->getSlug())
            ->execute();
    }

    /**
     * @return Etudiant[] Returns an array of Etudiant objects
     */

    public function findAllParticipatedAtLeastOneEvaluation() // ne retourne que les statuts qui ont des étudiants
    {
        return $this->getEntityManager()->createQuery('
        SELECT e
        FROM App\Entity\Etudiant e
        JOIN e.points p
        ')
            ->execute();
    }

    /**
     * @return Etudiant[] Returns an array of Etudiant objects
     */

    public function findAllWithStatut() // retourne les statuts, meme ceux qui n'ont pas d'étudiants
    {
        return $this->getEntityManager()->createQuery('
        SELECT e, s
        FROM App\Entity\Etudiant e
        LEFT JOIN e.statuts s
        ')
            ->execute();
    }

    /**
     * @return Etudiant[] Returns an array of Etudiant objects
     */

     public function findAllByOneStatutAndOneGroupe ($statut, $groupe)
     {
       return $this->getEntityManager()->createQuery('
       SELECT e
       FROM App\Entity\Etudiant e
       LEFT JOIN e.statuts s
       LEFT JOIN e.groupes g
       WHERE g.slug = :slugGroupe
       AND s.slug = :slugStatut
        ')
            ->setParameter('slugStatut', $statut->getSlug())
            ->setParameter('slugGroupe', $groupe->getSlug())
            ->execute();
     }

    // /**
    //  * @return Etudiant[] Returns an array of Etudiant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Etudiant
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
