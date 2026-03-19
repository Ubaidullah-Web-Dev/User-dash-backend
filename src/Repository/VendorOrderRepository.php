<?php

namespace App\Repository;

use App\Entity\VendorOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<VendorOrder>
 *
 * @method VendorOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method VendorOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method VendorOrder[]    findAll()
 * @method VendorOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendorOrderRepository extends ServiceEntityRepository implements PaginationInterface
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VendorOrder::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedFilterOrders(array $filters, int $companyId, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('vo')
            ->leftJoin('vo.product', 'p')
            ->leftJoin('p.category', 'c')
            ->andWhere('vo.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('vo.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);

        return $this->paginate($qb, $page, $limit);
    }

    private function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['status'])) {
            $qb->andWhere('vo.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['productName'])) {
            $qb->andWhere('p.name LIKE :productName')
                ->setParameter('productName', '%' . $filters['productName'] . '%');
        }

        if (!empty($filters['productId'])) {
            $qb->andWhere('p.id = :productId')
                ->setParameter('productId', $filters['productId']);
        }

        if (!empty($filters['orderId'])) {
            $orderIdStr = trim(str_ireplace('#SUP-', '', $filters['orderId']));
            if ($orderIdStr !== '') {
                $qb->andWhere('vo.id LIKE :orderId')
                    ->setParameter('orderId', '%' . $orderIdStr . '%');
            }
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('c.name LIKE :category OR c.id = :categoryId')
                ->setParameter('category', '%' . $filters['category'] . '%')
                ->setParameter('categoryId', is_numeric($filters['category']) ? $filters['category'] : -1);
        }
    }
}