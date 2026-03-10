<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository implements PaginationInterface
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedFilterProducts(array $filters, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->orderBy('p.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);

        return $this->paginate($qb, $page, $limit);
    }

    private function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $filters['category']);
        }

        if (!empty($filters['id'])) {
            $qb->andWhere('p.id = :id')
                ->setParameter('id', $filters['id']);
        }

        if (isset($filters['minPrice']) && $filters['minPrice'] !== '') {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', $filters['minPrice']);
        }

        if (isset($filters['maxPrice']) && $filters['maxPrice'] !== '') {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $isActive = $filters['status'] === 'active';
            $qb->andWhere('p.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }
    }

    public function bulkAssignToCategory(array $ids, \App\Entity\Category $category): void
    {
        if (empty($ids)) {
            return;
        }

        $this->createQueryBuilder('p')
            ->update()
            ->set('p.category', ':category')
            ->where('p.id IN (:ids)')
            ->setParameter('category', $category)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}