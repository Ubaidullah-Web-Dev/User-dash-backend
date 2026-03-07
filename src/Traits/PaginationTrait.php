<?php

namespace App\Traits;

use App\DTO\PaginatedResponseDto;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

trait PaginationTrait
{
    /**
     * Paginate a given QueryBuilder.
     *
     * @param QueryBuilder $qb The pre-configured QueryBuilder (filters, sorts applied).
     * @param int $page The current page number.
     * @param int $limit The number of items per page.
     * @return PaginatedResponseDto
     */
    public function paginate(QueryBuilder $qb, int $page = 1, int $limit = 10): PaginatedResponseDto
    {
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        // Use Doctrine Paginator for accurate total counts, especially helpful with joins
        $paginator = new Paginator($qb);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        $data = [];
        foreach ($paginator as $item) {
            $data[] = $item;
        }

        return new PaginatedResponseDto(
            data: $data,
            total: $total,
            page: $page,
            limit: $limit,
            pages: $pages > 0 ? $pages : 1
        );
    }
}