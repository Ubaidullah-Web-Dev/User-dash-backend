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
use App\Repository\CategoryRepository;
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

    #[Route('/products/{id}', name: 'admin_product_show', methods: ['GET'])]
    public function showProduct(Product $product): JsonResponse
    {
        return $this->json(ProductDTO::fromEntity($product));
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
        ValidatorInterface $validator,
        CategoryRepository $categoryRepo
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
        if ($updateDto->companyName !== null) $product->setCompanyName($updateDto->companyName);
        if ($updateDto->packSize !== null) $product->setPackSize($updateDto->packSize);
        if ($updateDto->purchasePrice !== null) $product->setPurchasePrice($updateDto->purchasePrice);
        if ($updateDto->expiryDate !== null) $product->setExpiryDate(new \DateTimeImmutable($updateDto->expiryDate));
        if ($updateDto->batchNumber !== null) $product->setBatchNumber($updateDto->batchNumber);
        if ($updateDto->minimumStock !== null) $product->setMinimumStock($updateDto->minimumStock);
        if ($updateDto->unit !== null) $product->setUnit($updateDto->unit);

        if ($updateDto->categoryId !== null) {
            $category = $categoryRepo->find($updateDto->categoryId);
            if ($category) {
                $product->setCategory($category);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Product updated successfully']);
    }

    #[Route('/categories', name: 'admin_category_create', methods: ['POST'])]
    public function createCategory(
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;

        if (!$name) {
            return $this->json(['message' => 'Category name is required'], Response::HTTP_BAD_REQUEST);
        }

        $category = new \App\Entity\Category();
        $this->updateCategoryFields($category, $data);

        $em->persist($category);
        $em->flush();

        // Optional initial product assignment
        $productIds = $data['productIds'] ?? [];
        if (!empty($productIds)) {
            $productRepo->bulkAssignToCategory($productIds, $category);
        }

        return $this->json([
            'message' => 'Category created successfully',
            'category' => $this->formatCategory($category)
        ], Response::HTTP_CREATED);
    }


    #[Route('/categories/{id}', name: 'admin_category_update', methods: ['PUT'])]
    public function updateCategory(
        \App\Entity\Category $category,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $this->updateCategoryFields($category, $data);
        
        $em->flush();

        return $this->json([
            'message' => 'Category updated successfully',
            'category' => $this->formatCategory($category)
        ]);
    }

    #[Route('/categories/{id}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function deleteCategory(\App\Entity\Category $category, EntityManagerInterface $em): JsonResponse
    {
        // Check if category has products
        if (!$category->getProducts()->isEmpty()) {
            return $this->json([
                'message' => 'Cannot delete category with associated products. Reassign products first.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->remove($category);
        $em->flush();

        return $this->json(['message' => 'Category deleted successfully']);
    }

    #[Route('/categories/{id}/products', name: 'admin_category_assign_products', methods: ['PATCH'])]
    public function assignProductsToCategory(
        \App\Entity\Category $category,
        Request $request,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $productIds = $data['productIds'] ?? [];

        if (empty($productIds)) {
            return $this->json(['message' => 'No products specified'], Response::HTTP_BAD_REQUEST);
        }

        $productRepo->bulkAssignToCategory($productIds, $category);

        return $this->json([
            'message' => sprintf('Assigned %d products to category %s', count($productIds), $category->getName())
        ]);
    }

    #[Route('/categories/products/{productId}/unlink', name: 'admin_category_unlink_product', methods: ['DELETE'])]
    public function unlinkProductFromCategory(
        int $productId,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $product = $productRepo->find($productId);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $product->setCategory(null);
        $em->flush();

        return $this->json(['message' => 'Product unlinked successfully']);
    }

    #[Route('/categories/stats', name: 'admin_category_stats', methods: ['GET'])]
    public function listCategoriesWithStats(\App\Repository\CategoryRepository $categoryRepo, Request $request): JsonResponse
    {
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $filters = ['search' => $search];
        $paginatedResponse = $categoryRepo->getPaginatedFilterCategories($filters, $page, $limit);

        $data = array_map(function (\App\Entity\Category $category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'image' => $category->getImage(),
                'productCount' => $category->getProducts()->count()
            ];
        }, $paginatedResponse->data);

        return $this->json([
            'data' => $data,
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    private function updateCategoryFields(\App\Entity\Category $category, array $data): void
    {
        if (isset($data['name'])) {
            $category->setName($data['name']);
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
            $category->setSlug($slug);
        }
        
        if (isset($data['image'])) {
            $category->setImage($data['image']);
        }
    }

    private function formatCategory(\App\Entity\Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'image' => $category->getImage()
        ];
    }



    #[Route('/orders/walk-in', name: 'admin_order_walk_in', methods: ['POST'])]
    public function walkInOrder(
        Request $request, 
        EntityManagerInterface $em, 
        ProductRepository $productRepo,
        \App\Repository\RegisteredCustomerRepository $registeredCustomerRepo
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $customerNameRaw = $data['customerName'] ?? null;
        $customerName = trim((string)$customerNameRaw) !== '' ? trim((string)$customerNameRaw) : 'Walk-In Customer';
        $phoneRaw = $data['phone'] ?? null;
        $phone = trim((string)$phoneRaw) !== '' ? trim((string)$phoneRaw) : null;
        $items = $data['items'] ?? [];
        $amountTendered = $data['amountTendered'] ?? null;
        $changeDue = $data['changeDue'] ?? null;
        $discountPercentage = $data['discountPercentage'] ?? null;
        $discountAmount = $data['discountAmount'] ?? null;

        if (empty($items)) {
            return $this->json(['message' => 'Sale must contain at least one item'], Response::HTTP_BAD_REQUEST);
        }

        $order = new \App\Entity\Order();
        $order->setCustomerName($customerName);
        $order->setPhone($phone ?? 'N/A');
        $order->setAddress('In-Store POS');
        
        $adminUser = $this->getUser();
        if (!$adminUser) {
            return $this->json(['message' => 'Unauthorized. Session expired.'], Response::HTTP_UNAUTHORIZED);
        }
        $order->setUser($adminUser);

        $total = 0;

        foreach ($items as $itemData) {
            $productId = $itemData['productId'] ?? null;
            $quantity = $itemData['quantity'] ?? 0;

            if (!$productId || $quantity <= 0) {
                return $this->json(['message' => 'Invalid product or quantity'], Response::HTTP_BAD_REQUEST);
            }

            $product = $productRepo->find($productId);
            if (!$product) {
                return $this->json(['message' => "Product ID $productId not found in global registry"], Response::HTTP_BAD_REQUEST);
            }

            if ($product->getStock() < $quantity) {
                return $this->json(['message' => sprintf('Insufficient stock for %s. Only %d units available.', $product->getName(), $product->getStock())], Response::HTTP_BAD_REQUEST);
            }

            $itemTotal = $product->getPrice() * $quantity;
            $total += $itemTotal;

            $orderItem = new \App\Entity\OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setPrice($product->getPrice());
            
            $order->addItem($orderItem);

            // Deduct stock
            $product->setStock($product->getStock() - $quantity);
        }

        $order->setTotal($total);
        $order->setAmountTendered($amountTendered);
        $order->setChangeDue($changeDue);
        $order->setDiscountPercentage($discountPercentage);
        $order->setDiscountAmount($discountAmount);

        $registeredCustomerId = $data['registeredCustomerId'] ?? null;
        if ($registeredCustomerId) {
            $registeredCustomer = $registeredCustomerRepo->find($registeredCustomerId);
            if ($registeredCustomer) {
                // If change is negative, customer underpaid. Add to outstanding balance.
                if ($changeDue < 0) {
                    $registeredCustomer->addRemainingBalance(abs($changeDue));
                }
                $registeredCustomer->addOrder($order);
                // Total spent updated inside addOrder
            }
        } elseif ($phone) {
            $registeredCustomer = $registeredCustomerRepo->findOneBy(['phone' => $phone]);
            if (!$registeredCustomer) {
                $registeredCustomer = new \App\Entity\RegisteredCustomer();
                $registeredCustomer->setPhone($phone);
                $registeredCustomer->setName($customerName);
                $em->persist($registeredCustomer);
            }
            if ($changeDue < 0) {
                $registeredCustomer->addRemainingBalance(abs($changeDue));
            }
            $registeredCustomer->addOrder($order);
        } else {
            $guestUser = new \App\Entity\GuestUser();
            $guestUser->setName($customerName);
            $em->persist($guestUser);
            
            $guestUser->addOrder($order);
        }

        $em->persist($order);
        $em->flush();

        return $this->json([
            'message' => 'Sale completed successfully',
            'orderId' => $order->getId()
        ], Response::HTTP_CREATED);
    }
}