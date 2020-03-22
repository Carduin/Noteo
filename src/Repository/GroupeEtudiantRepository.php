<?php

namespace App\Repository;

use App\Entity\GroupeEtudiant;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method GroupeEtudiant|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupeEtudiant|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupeEtudiant[]    findAll()
 * @method GroupeEtudiant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupeEtudiantRepository extends NestedTreeRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em,$em->getClassMetaData(GroupeEtudiant::class));
    }

    /**
    * @return GroupeEtudiant[] Returns an array of GroupeEtudiant objects
    */

    public function findAllWithoutNonEvaluableGroups()
    {
        return $this->createQueryBuilder('g')
            ->addSelect('et')
            ->addSelect('en')
            ->join('g.enseignant', 'en')
            ->leftJoin('g.etudiants', 'et')
            ->where('g.estEvaluable = :param')
            ->setParameter('param', true)
            ->orderBy('g.lft', 'asc')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return GroupeEtudiant[] Returns an array of GroupeEtudiant objects
     */

    public function findAllFromNode($node)
    {
        return $this->createQueryBuilder('g')
            ->addSelect('et')
            ->addSelect('en')
            ->join('g.enseignant', 'en')
            ->leftJoin('g.etudiants', 'et')
            ->where('g.rgt <= :right')
            ->andWhere('g.lft >= :left')
            ->setParameter('right', $node->getRgt())
            ->setParameter('left', $node->getLft())
            ->orderBy('g.lft', 'asc')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return GroupeEtudiant[] Returns an array of GroupeEtudiant objects
     */

    public function findAllOrderedAndWithoutSpace()
    {
        return $this->createQueryBuilder('g')
            ->addSelect('et')
            ->addSelect('en')
            ->join('g.enseignant', 'en')
            ->leftJoin('g.etudiants', 'et')
            ->where('g.slug != :param')
            ->setParameter('param', 'etudiants-non-affectes')
            ->orderBy('g.lft', 'asc')
            ->getQuery()
            ->getResult()
            ;
    }

    // /**
    //  * @return GroupeEtudiant[] Returns an array of GroupeEtudiant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GroupeEtudiant
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
