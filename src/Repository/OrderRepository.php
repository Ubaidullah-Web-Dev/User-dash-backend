<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository implements PaginationInterface
{
    use PaginationTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedOrders(array $filters, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('o.customerName LIKE :search OR o.phone LIKE :search OR o.id LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $this->paginate($qb, $page, $limit);
    }
}