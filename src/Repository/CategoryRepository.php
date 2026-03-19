<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Interfaces\PaginationInterface;
use App\Traits\PaginationTrait;
use App\DTO\PaginatedResponseDto;

/**
 * @extends ServiceEntityRepository<Category>
 *
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends ServiceEntityRepository implements PaginationInterface
{
    use PaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return PaginatedResponseDto
     */
    public function getPaginatedFilterCategories(array $filters, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        if (!empty($filters['companyId'])) {
            $qb->andWhere('c.company = :companyId')
                ->setParameter('companyId', $filters['companyId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('c.name LIKE :search OR c.slug LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $this->paginate($qb, $page, $limit);
    }
}