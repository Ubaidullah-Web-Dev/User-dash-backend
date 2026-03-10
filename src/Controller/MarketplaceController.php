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
use App\DTO\ProductCreateDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        SluggerInterface $slugger,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        $dto = new ProductCreateDTO();
        $dto->name = $data['name'] ?? null;
        $dto->description = $data['description'] ?? null;
        $dto->price = isset($data['price']) ? (float)$data['price'] : null;
        $dto->stock = isset($data['stock']) ? (int)$data['stock'] : null;
        $dto->unit = $data['unit'] ?? null;
        $dto->category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $dto->isRecommended = $data['isRecommended'] ?? false;
        $dto->images = $data['images'] ?? [];
        $dto->companyName = $data['companyName'] ?? null;
        $dto->packSize = $data['packSize'] ?? null;
        $dto->purchasePrice = isset($data['purchasePrice']) ? (float)$data['purchasePrice'] : null;
        $dto->expiryDate = $data['expiryDate'] ?? null;
        $dto->batchNumber = $data['batchNumber'] ?? null;
        $dto->minimumStock = isset($data['minimumStock']) ? (int)$data['minimumStock'] : 0;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }
        
        $product = new Product();
        $product->setName($dto->name);
        $product->setSlug(strtolower($slugger->slug($product->getName())) . '-' . uniqid());
        $product->setDescription($dto->description);
        $product->setPrice($dto->price);
        $product->setStock($dto->stock);
        $product->setIsRecommended($dto->isRecommended);
        $product->setUser($user);
        $product->setCompanyName($dto->companyName);
        $product->setPackSize($dto->packSize);
        if ($dto->purchasePrice !== null) $product->setPurchasePrice((string)$dto->purchasePrice);
        if ($dto->expiryDate !== null) $product->setExpiryDate(new \DateTimeImmutable($dto->expiryDate));
        $product->setBatchNumber($dto->batchNumber);
        $product->setMinimumStock($dto->minimumStock);

        if ($dto->unit !== null) {
            $product->setUnit(trim($dto->unit));
        }

        $category = $categoryRepository->find($dto->category_id);
        if (!$category) {
            return $this->json(['message' => 'Invalid category'], Response::HTTP_BAD_REQUEST);
        }
        $product->setCategory($category);

        if (!empty($dto->images)) {
            foreach ($dto->images as $url) {
                if (empty(trim($url))) continue;
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