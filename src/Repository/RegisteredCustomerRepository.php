<?php

namespace App\Repository;

use App\Entity\RegisteredCustomer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<RegisteredCustomer>
 *
 * @method RegisteredCustomer|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegisteredCustomer|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegisteredCustomer[]    findAll()
 * @method RegisteredCustomer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegisteredCustomerRepository extends ServiceEntityRepository implements PaginationInterface
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegisteredCustomer::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedCustomers(array $filters, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('c.name LIKE :search OR c.phone LIKE :search OR c.labName LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $this->paginate($qb, $page, $limit);
    }
}