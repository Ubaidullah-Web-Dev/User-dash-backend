<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\DTO\ProductDTO;
use App\DTO\UserDTO;
use App\DTO\ProductUpdateDTO;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard_stats', methods: ['GET'])]
    public function dashboard(ProductRepository $productRepo, UserRepository $userRepo): JsonResponse
    {
        return $this->json([
            'stats' => [
                'total_products' => $productRepo->count([]),
                'total_users' => $userRepo->count([]),
                'total_sales' => 0,
                'pending_orders' => 0,
            ]
        ]);
    }

    #[Route('/products', name: 'admin_product_list', methods: ['GET'])]
    public function listProducts(ProductRepository $productRepo, Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query->get('search'),
            'category' => $request->query->get('category'),
            'id' => $request->query->get('id'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'status' => $request->query->get('status', 'all'),
        ];

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $paginatedResponse = $productRepo->getPaginatedFilterProducts($filters, $page, $limit);
        
        $formattedData = array_map(fn(Product $product) => ProductDTO::fromEntity($product), $paginatedResponse->data);

        return $this->json([
            'data' => $formattedData,
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/products/{id}', name: 'admin_product_delete', methods: ['DELETE'])]
    public function deleteProduct(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($product);
        $em->flush();

        return $this->json(['message' => 'Product deleted by admin']);
    }

    #[Route('/products/{id}/toggle-active', name: 'admin_product_toggle_active', methods: ['PATCH'])]
    public function toggleActive(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $product->setIsActive(!$product->isActive());
        $em->flush();

        return $this->json([
            'message' => 'Product status updated',
            'isActive' => $product->isActive()
        ]);
    }

    #[Route('/products/{id}', name: 'admin_product_edit', methods: ['PUT'])]
    public function editProduct(
        Product $product, 
        Request $request, 
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse
    {
        try {
            /** @var ProductUpdateDTO $updateDto */
            $updateDto = $serializer->deserialize($request->getContent(), ProductUpdateDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($updateDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        
        if ($updateDto->name !== null) $product->setName($updateDto->name);
        if ($updateDto->description !== null) $product->setDescription($updateDto->description);
        if ($updateDto->price !== null) $product->setPrice($updateDto->price);
        if ($updateDto->stock !== null) $product->setStock($updateDto->stock);
        if ($updateDto->isRecommended !== null) $product->setIsRecommended($updateDto->isRecommended);

        $em->flush();

        return $this->json(['message' => 'Product updated successfully']);
    }

    #[Route('/categories', name: 'admin_category_create', methods: ['POST'])]
    public function createCategory(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;

        if (!$name) {
            return $this->json(['message' => 'Category name is required'], Response::HTTP_BAD_REQUEST);
        }

        $category = new \App\Entity\Category();
        $category->setName($name);
        
        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $category->setSlug($slug);
        
        if (isset($data['image'])) {
            $category->setImage($data['image']);
        }

        $em->persist($category);
        $em->flush();

        return $this->json([
            'message' => 'Category created successfully',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/users', name: 'admin_user_list', methods: ['GET'])]
    public function listUsers(UserRepository $userRepo): JsonResponse
    {
        $users = $userRepo->findAll();
        $data = array_map(fn(User $user) => UserDTO::fromEntity($user), $users);

        return $this->json($data);
    }
}