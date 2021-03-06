<?php

namespace App\Repository;

use App\Entity\ShortenedURL;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ShortenedURL|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShortenedURL|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShortenedURL[]    findAll()
 * @method ShortenedURL[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShortenedURLRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShortenedURL::class);
    }
}
