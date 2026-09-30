<?php

namespace App\Controller;

use App\Presenter\CategoryPresenter;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository, CategoryPresenter $presenter): JsonResponse
    {
        return $this->json(array_map($presenter->toArray(...), $categoryRepository->findAll()));
    }
}