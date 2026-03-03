<?php

namespace App\Repository;

use App\Entity\VendorOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VendorOrder>
 *
 * @method VendorOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method VendorOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method VendorOrder[]    findAll()
 * @method VendorOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendorOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VendorOrder::class);
    }
}