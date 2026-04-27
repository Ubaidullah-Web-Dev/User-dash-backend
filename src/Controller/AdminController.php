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
use App\Service\TenantContext;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard_stats', methods: ['GET'])]
    public function dashboard(ProductRepository $productRepo, UserRepository $userRepo, TenantContext $tenantContext): JsonResponse
    {
        $companyId = $tenantContext->getCurrentCompanyId();
        return $this->json([
            'stats' => [
                'total_products' => $productRepo->count(['company' => $companyId]),
                'total_users' => $userRepo->count(['company' => $companyId]),
                'total_sales' => 0,
                'pending_orders' => 0,
            ]
        ]);
    }

    #[Route('/products', name: 'admin_product_list', methods: ['GET'])]
    public function listProducts(ProductRepository $productRepo, Request $request, TenantContext $tenantContext): JsonResponse
    {
        $filters = [
            'search' => $request->query->get('search'),
            'category' => $request->query->get('category'),
            'id' => $request->query->get('id'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'status' => $request->query->get('status', 'active'),
        ];

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $companyId = $tenantContext->getCurrentCompanyId();

        $paginatedResponse = $productRepo->getPaginatedFilterProducts($filters, $companyId, $page, $limit);

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
        $product->setIsActive(false);
        $em->flush();

        return $this->json(['message' => 'Product moved to recovery.']);
    }

    #[Route('/products/{id}/restore', name: 'admin_product_restore', methods: ['PATCH'])]
    public function restoreProduct(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $product->setIsActive(true);
        $em->flush();

        return $this->json(['message' => 'Product restored successfully']);
    }

    #[Route('/products/{id}/permanent', name: 'admin_product_permanent_delete', methods: ['DELETE'])]
    public function permanentDeleteProduct(Product $product, EntityManagerInterface $em): JsonResponse
    {
        // Check if there are any order items for this product
        $orderItemRepo = $em->getRepository(\App\Entity\OrderItem::class);
        $orderItemCount = $orderItemRepo->count(['product' => $product]);

        if ($orderItemCount > 0) {
            return $this->json(['message' => 'Product has associated orders and cannot be permanently deleted.'], Response::HTTP_BAD_REQUEST);
        }

        $em->remove($product);
        $em->flush();

        return $this->json(['message' => 'Product permanently deleted']);
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
    ): JsonResponse {
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

        if ($updateDto->name !== null)
            $product->setName($updateDto->name);
        if ($updateDto->description !== null)
            $product->setDescription($updateDto->description);
        if ($updateDto->price !== null)
            $product->setPrice($updateDto->price);
        if ($updateDto->stock !== null)
            $product->setStock($updateDto->stock);
        if ($updateDto->isRecommended !== null)
            $product->setIsRecommended($updateDto->isRecommended);
        if ($updateDto->companyName !== null)
            $product->setCompanyName($updateDto->companyName);
        if ($updateDto->packSize !== null)
            $product->setPackSize($updateDto->packSize);
        if ($updateDto->purchasePrice !== null)
            $product->setPurchasePrice($updateDto->purchasePrice);
        if ($updateDto->expiryDate !== null)
            $product->setExpiryDate(new \DateTimeImmutable($updateDto->expiryDate));
        if ($updateDto->batchNumber !== null)
            $product->setBatchNumber($updateDto->batchNumber);
        if ($updateDto->minimumStock !== null)
            $product->setMinimumStock($updateDto->minimumStock);
        if ($updateDto->unit !== null)
            $product->setUnit($updateDto->unit);

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
        ProductRepository $productRepo,
        \App\Service\TenantContext $tenantContext,
        \App\Repository\VendorRepository $vendorRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $vendorId = $data['vendorId'] ?? null;

        if (!$name) {
            return $this->json(['message' => 'Category name is required'], Response::HTTP_BAD_REQUEST);
        }

        $company = $tenantContext->getCurrentCompany();
        if (!$company) {
            return $this->json(['message' => 'Company context not found'], Response::HTTP_BAD_REQUEST);
        }

        $category = new \App\Entity\Category();
        $category->setCompany($company);
        $this->updateCategoryFields($category, $data);

        $em->persist($category);

        // Handle vendor association
        if ($vendorId) {
            $vendor = $vendorRepo->find($vendorId);
            if ($vendor && $vendor->getCompany()->getId() === $company->getId()) {
                $vendor->setCategory($category);
            }
        }

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
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo,
        \App\Service\TenantContext $tenantContext,
        \App\Repository\VendorRepository $vendorRepo
    ): JsonResponse {
        $category = $categoryRepo->find($id);
        if (!$category || $category->getCompany()->getId() !== $tenantContext->getCurrentCompanyId()) {
            return $this->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateCategoryFields($category, $data);

        $vendorId = $data['vendorId'] ?? null;
        if ($vendorId !== null) {
            // Unlink current vendors if changing (simple logic: one category can have multiple vendors but here we might want to link/unlink specific ones)
            // User said "link it to a vendor", which implies a primary association or setting it on a specific vendor.

            // First unlink vendors currently linked to this category for this company
            $currentVendors = $vendorRepo->findBy(['category' => $category]);
            foreach ($currentVendors as $v) {
                $v->setCategory(null);
            }

            if ($vendorId > 0) {
                $vendor = $vendorRepo->find($vendorId);
                if ($vendor && $vendor->getCompany()->getId() === $tenantContext->getCurrentCompanyId()) {
                    $vendor->setCategory($category);
                }
            }
        }

        $em->flush();

        return $this->json([
            'message' => 'Category updated successfully',
            'category' => $this->formatCategory($category)
        ]);
    }

    #[Route('/categories/{id}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function deleteCategory(int $id, CategoryRepository $categoryRepo, \App\Service\TenantContext $tenantContext, EntityManagerInterface $em): JsonResponse
    {
        $category = $categoryRepo->find($id);
        if (!$category || $category->getCompany()->getId() !== $tenantContext->getCurrentCompanyId()) {
            return $this->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

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
        int $id,
        Request $request,
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo,
        \App\Service\TenantContext $tenantContext,
        EntityManagerInterface $em
    ): JsonResponse {
        $category = $categoryRepo->find($id);
        if (!$category || $category->getCompany()->getId() !== $tenantContext->getCurrentCompanyId()) {
            return $this->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $productIds = $data['productIds'] ?? [];

        if (empty($productIds)) {
            return $this->json(['message' => 'No products specified'], Response::HTTP_BAD_REQUEST);
        }

        // Filter products to ensure they belong to the same company
        $validProductIds = [];
        foreach ($productIds as $pId) {
            $p = $productRepo->find($pId);
            if ($p && $p->getCompany()->getId() === $tenantContext->getCurrentCompanyId()) {
                $validProductIds[] = $pId;
            }
        }

        if (empty($validProductIds)) {
            return $this->json(['message' => 'No valid products found for this company'], Response::HTTP_BAD_REQUEST);
        }

        $productRepo->bulkAssignToCategory($validProductIds, $category);

        return $this->json([
            'message' => sprintf('Assigned %d products to category %s', count($validProductIds), $category->getName())
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
    public function listCategoriesWithStats(
        CategoryRepository $categoryRepo,
        Request $request,
        \App\Service\TenantContext $tenantContext
    ): JsonResponse {
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $filters = [
            'search' => $search,
            'companyId' => $tenantContext->getCurrentCompanyId()
        ];
        $paginatedResponse = $categoryRepo->getPaginatedFilterCategories($filters, $page, $limit);

        $data = array_map(function (\App\Entity\Category $category) {
            // Find linked vendor (we assume simple 1-1 for now as per user intent)
            $vendor = null;
            if (!$category->getVendors()->isEmpty()) {
                $v = $category->getVendors()->first();
                $vendor = [
                    'id' => $v->getId(),
                    'name' => $v->getName()
                ];
            }

            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'image' => $category->getImage(),
                'productCount' => $category->getProducts()->count(),
                'vendor' => $vendor
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
        \App\Repository\RegisteredCustomerRepository $registeredCustomerRepo,
        TenantContext $tenantContext
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $customerNameRaw = $data['customerName'] ?? null;
        $customerName = trim((string) $customerNameRaw) !== '' ? trim((string) $customerNameRaw) : 'Walk-In Customer';
        $phoneRaw = $data['phone'] ?? null;
        $phone = trim((string) $phoneRaw) !== '' ? trim((string) $phoneRaw) : null;
        $items = $data['items'] ?? [];
        $amountTendered = $data['amountTendered'] ?? null;
        $changeDue = $data['changeDue'] ?? null;
        $discountPercentage = $data['discountPercentage'] ?? null;
        $discountAmount = $data['discountAmount'] ?? null;
        $remarks = $data['remarks'] ?? null;

        if (empty($items)) {
            return $this->json(['message' => 'Sale must contain at least one item'], Response::HTTP_BAD_REQUEST);
        }

        $company = $tenantContext->getCurrentCompany();
        $companyId = $tenantContext->getCurrentCompanyId();

        $order = new \App\Entity\Order();
        $order->setCustomerName($customerName);
        $order->setPhone($phone ?? 'N/A');
        $order->setAddress('In-Store POS');
        $order->setCompany($company);
        $order->setRemarks($remarks);

        $adminUser = $this->getUser();
        if (!$adminUser) {
            return $this->json(['message' => 'Unauthorized. Session expired.'], Response::HTTP_UNAUTHORIZED);
        }
        $order->setUser($adminUser);

        $total = 0;
        $totalDiscountAmount = 0;

        foreach ($items as $itemData) {
            $productId = $itemData['productId'] ?? null;
            $quantity = $itemData['quantity'] ?? 0;
            $itemDiscountPercentage = (float) ($itemData['discountPercentage'] ?? 0);

            if (!$productId || $quantity <= 0) {
                return $this->json(['message' => 'Invalid product or quantity'], Response::HTTP_BAD_REQUEST);
            }

            $product = $productRepo->findOneBy(['id' => $productId, 'company' => $companyId]);
            if (!$product) {
                return $this->json(['message' => "Product ID $productId not found or access denied"], Response::HTTP_BAD_REQUEST);
            }

            if (!$product->isActive()) {
                return $this->json(['message' => sprintf('Product %s is disabled and cannot be sold.', $product->getName())], Response::HTTP_BAD_REQUEST);
            }

            if ($product->getStock() < $quantity) {
                return $this->json(['message' => sprintf('Insufficient stock for %s. Only %d units available.', $product->getName(), $product->getStock())], Response::HTTP_BAD_REQUEST);
            }

            $itemSubtotal = $product->getPrice() * $quantity;
            $itemDiscountAmount = ($itemSubtotal * $itemDiscountPercentage) / 100;

            $total += $itemSubtotal;
            $totalDiscountAmount += $itemDiscountAmount;

            $orderItem = new \App\Entity\OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setPrice($product->getPrice());
            $orderItem->setDiscountPercentage($itemDiscountPercentage);
            $orderItem->setDiscountAmount($itemDiscountAmount);
            $orderItem->setCompany($company);

            $order->addItem($orderItem);

            // Deduct stock
            $product->setStock($product->getStock() - $quantity);
        }

        $order->setTotal($total - $totalDiscountAmount);
        $order->setAmountTendered($amountTendered);
        $order->setChangeDue($changeDue);
        $order->setDiscountPercentage(null); // No longer a single percentage
        $order->setDiscountAmount($totalDiscountAmount);

        $previousBalancePayment = (float) ($data['previousBalancePayment'] ?? 0);
        $order->setPreviousBalancePayment($previousBalancePayment);

        $registeredCustomerId = $data['registeredCustomerId'] ?? null;
        if ($registeredCustomerId) {
            $registeredCustomer = $registeredCustomerRepo->findOneBy(['id' => $registeredCustomerId, 'company' => $companyId]);
            if ($registeredCustomer) {
                if (!$registeredCustomer->isActive()) {
                    return $this->json(['message' => 'The selected customer is disabled and cannot make new orders.'], Response::HTTP_BAD_REQUEST);
                }
                // If change is negative, customer underpaid. Add to outstanding balance.
                if ($changeDue < 0) {
                    $registeredCustomer->addRemainingBalance(abs($changeDue));
                }

                // Subtract the previous balance payment
                if ($previousBalancePayment > 0) {
                    $registeredCustomer->setRemainingBalance(
                        $registeredCustomer->getRemainingBalance() - $previousBalancePayment
                    );
                    
                    // Distribute previous balance payment to unpaid orders
                    $paymentToDistribute = $previousBalancePayment;
                    $unpaidOrders = $em->getRepository(\App\Entity\Order::class)->createQueryBuilder('o')
                        ->where('o.registeredCustomer = :customer')
                        ->andWhere('o.changeDue < 0')
                        ->setParameter('customer', $registeredCustomer)
                        ->orderBy('o.createdAt', 'ASC')
                        ->getQuery()
                        ->getResult();

                    foreach ($unpaidOrders as $unpaidOrder) {
                        $orderPending = abs($unpaidOrder->getChangeDue());
                        if ($paymentToDistribute >= $orderPending) {
                            $unpaidOrder->setChangeDue(0);
                            $unpaidOrder->setAmountTendered($unpaidOrder->getAmountTendered() + $orderPending);
                            $paymentToDistribute -= $orderPending;
                        } else {
                            $unpaidOrder->setChangeDue(-($orderPending - $paymentToDistribute));
                            $unpaidOrder->setAmountTendered($unpaidOrder->getAmountTendered() + $paymentToDistribute);
                            $paymentToDistribute = 0;
                            break;
                        }
                    }
                }

                $registeredCustomer->addOrder($order);
                // Total spent updated inside addOrder
            }
        } elseif ($phone) {
            $registeredCustomer = $registeredCustomerRepo->findOneBy(['phone' => $phone, 'company' => $companyId]);
            if (!$registeredCustomer) {
                $registeredCustomer = new \App\Entity\RegisteredCustomer();
                $registeredCustomer->setPhone($phone);
                $registeredCustomer->setName($customerName);
                $registeredCustomer->setCompany($company);
                $em->persist($registeredCustomer);
            }
            if ($changeDue < 0) {
                $registeredCustomer->addRemainingBalance(abs($changeDue));
            }

            if ($previousBalancePayment > 0) {
                $registeredCustomer->setRemainingBalance(
                    $registeredCustomer->getRemainingBalance() - $previousBalancePayment
                );
                
                // Distribute previous balance payment to unpaid orders
                $paymentToDistribute = $previousBalancePayment;
                $unpaidOrders = $em->getRepository(\App\Entity\Order::class)->createQueryBuilder('o')
                    ->where('o.registeredCustomer = :customer')
                    ->andWhere('o.changeDue < 0')
                    ->setParameter('customer', $registeredCustomer)
                    ->orderBy('o.createdAt', 'ASC')
                    ->getQuery()
                    ->getResult();

                foreach ($unpaidOrders as $unpaidOrder) {
                    $orderPending = abs($unpaidOrder->getChangeDue());
                    if ($paymentToDistribute >= $orderPending) {
                        $unpaidOrder->setChangeDue(0);
                        $unpaidOrder->setAmountTendered($unpaidOrder->getAmountTendered() + $orderPending);
                        $paymentToDistribute -= $orderPending;
                    } else {
                        $unpaidOrder->setChangeDue(-($orderPending - $paymentToDistribute));
                        $unpaidOrder->setAmountTendered($unpaidOrder->getAmountTendered() + $paymentToDistribute);
                        $paymentToDistribute = 0;
                        break;
                    }
                }
            }

            $registeredCustomer->addOrder($order);
        } else {
            $guestUser = new \App\Entity\GuestUser();
            $guestUser->setName($customerName);
            $guestUser->setCompany($company);
            $em->persist($guestUser);

            $guestUser->addOrder($order);
        }

        try {
            $em->persist($order);
            $em->flush();
        } catch (\Exception $e) {
            return $this->json(['message' => 'Checkout failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Sale completed successfully',
            'orderId' => $order->getId()
        ], Response::HTTP_CREATED);
    }
}
