<?php

namespace App\Repository;

use App\Entity\RegisteredCustomer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegisteredCustomer>
 *
 * @method RegisteredCustomer|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegisteredCustomer|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegisteredCustomer[]    findAll()
 * @method RegisteredCustomer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegisteredCustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegisteredCustomer::class);
    }
}