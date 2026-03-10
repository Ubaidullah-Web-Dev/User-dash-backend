<?php

namespace App\Repository;

use App\Entity\GuestUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuestUser>
 *
 * @method GuestUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method GuestUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method GuestUser[]    findAll()
 * @method GuestUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GuestUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuestUser::class);
    }
}