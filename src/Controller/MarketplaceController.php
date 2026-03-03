<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\DTO\ProductDTO;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/products')]
class MarketplaceController extends AbstractController
{
    #[Route('', name: 'product_list', methods: ['GET'])]
    public function list(ProductRepository $productRepository, CategoryRepository $categoryRepository, Request $request): JsonResponse
    {
        $search = $request->query->get('search');
        $categoryId = $request->query->get('category');
        $recommended = $request->query->get('recommended');

        $criteria = [];
        if ($categoryId) {
            if (is_numeric($categoryId)) {
                $category = $categoryRepository->find($categoryId);
            } else {
                $category = $categoryRepository->findOneBy(['slug' => $categoryId]);
            }

            if ($category) {
                $criteria['category'] = $category;
            } else {
                return $this->json([]);
            }
        }
        if ($recommended) {
            $criteria['isRecommended'] = true;
        }

        if ($search) {
            $products = $productRepository->createQueryBuilder('p')
                ->where('p.name LIKE :search OR p.description LIKE :search')
                ->andWhere('p.isActive = :active')
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('active', true)
                ->getQuery()
                ->getResult();
        } else {
            $criteria['isActive'] = true;
            $products = $productRepository->findBy($criteria, ['createdAt' => 'DESC']);
        }

        $data = array_map(fn(Product $product) => ProductDTO::fromEntity($product), $products);

        return $this->json($data);
    }

    #[Route('/{slug}', name: 'product_show', methods: ['GET'], priority: -1)]
    public function show(Product $product): JsonResponse
    {
        return $this->json(ProductDTO::fromEntity($product));
    }

    #[Route('', name: 'product_create', methods: ['POST'])]
    public function create(
        Request $request, 
        EntityManagerInterface $entityManager, 
        CategoryRepository $categoryRepository,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        $product = new Product();
        $product->setName($data['name'] ?? 'Untitled');
        $product->setSlug(strtolower($slugger->slug($product->getName())) . '-' . uniqid());
        $product->setDescription($data['description'] ?? '');
        $product->setPrice($data['price'] ?? '0.00');
        $product->setStock($data['stock'] ?? 0);
        $product->setIsRecommended($data['isRecommended'] ?? false);
        $product->setUser($user);

        if (isset($data['unit']) && !empty($data['unit'])) {
            $unit = trim($data['unit']);
            if (!preg_match('/^[a-zA-Z0-9 ]{1,20}$/', $unit)) {
                return $this->json(['message' => 'Invalid unit format. Use max 20 alphanumeric characters.'], Response::HTTP_BAD_REQUEST);
            }
            $product->setUnit($unit);
        }

        $category = $categoryRepository->find($data['category_id'] ?? 0);
        if (!$category) {
            return $this->json(['message' => 'Invalid category'], Response::HTTP_BAD_REQUEST);
        }
        $product->setCategory($category);

        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $url) {
                $image = new ProductImage();
                $image->setUrl($url);
                $product->addImage($image);
            }
        }

        $entityManager->persist($product);
        $entityManager->flush();

        return $this->json(['message' => 'Post Ad created successfully', 'id' => $product->getId()], Response::HTTP_CREATED);
    }

    #[Route('/my-ads', name: 'product_my_ads', methods: ['GET'])]
    public function myAds(ProductRepository $productRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $products = $productRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $data = array_map(fn(Product $product) => ProductDTO::fromEntity($product), $products);

        return $this->json($data);
    }
}