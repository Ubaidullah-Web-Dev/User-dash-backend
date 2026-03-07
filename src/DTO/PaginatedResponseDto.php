<?php

namespace App\DTO;

class PaginatedResponseDto
{
    public array $data;
    public int $total;
    public int $page;
    public int $limit;
    public int $pages;

    public function __construct(array $data, int $total, int $page, int $limit, int $pages)
    {
        $this->data = $data;
        $this->total = $total;
        $this->page = $page;
        $this->limit = $limit;
        $this->pages = $pages;
    }
}