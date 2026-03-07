<?php

namespace App\Interfaces;

use App\DTO\PaginatedResponseDto;
use Doctrine\ORM\QueryBuilder;

interface PaginationInterface
{
    /**
     * Paginate a given QueryBuilder.
     *
     * @param QueryBuilder $qb The pre-configured QueryBuilder (filters, sorts applied).
     * @param int $page The current page number.
     * @param int $limit The number of items per page.
     * @return PaginatedResponseDto
     */
    public function paginate(QueryBuilder $qb, int $page = 1, int $limit = 10): PaginatedResponseDto;
}