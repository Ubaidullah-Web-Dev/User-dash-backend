<?php

namespace App\Repository;

use App\Entity\LabExpense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<LabExpense>
 *
 * @method LabExpense|null find($id, $lockMode = null, $lockVersion = null)
 * @method LabExpense|null findOneBy(array $criteria, array $orderBy = null)
 * @method LabExpense[]    findAll()
 * @method LabExpense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LabExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LabExpense::class);
    }

    public function save(LabExpense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LabExpense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getPaginatedExpenses(array $filters, int $companyId, int $page = 1, int $limit = 10): object
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('e.createdAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['startDate'])) {
            $qb->andWhere('e.expenseDate >= :startDate')
               ->setParameter('startDate', new \DateTime($filters['startDate']));
        }

        if (!empty($filters['endDate'])) {
            $endDate = new \DateTime($filters['endDate']);
            $endDate->setTime(23, 59, 59);
            $qb->andWhere('e.expenseDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $pagesCount = ceil($totalItems / $limit);

        return (object)[
            'data' => iterator_to_array($paginator),
            'total' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pagesCount ?: 1,
        ];
    }
}
