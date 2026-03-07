<?php

namespace App\Controller\Admin;

use App\Entity\Vendor;
use App\Entity\VendorOrder;
use App\Entity\Product;
use App\Entity\Category;
use App\DTO\VendorCreateDTO;
use App\DTO\VendorOrderCreateDTO;
use App\DTO\VendorOrderStatusUpdateDTO;
use App\Repository\VendorRepository;
use App\Repository\VendorOrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class VendorController extends AbstractController
{
    #[Route('/vendors', name: 'admin_vendors_list', methods: ['GET'])]
    public function listVendors(Request $request, VendorRepository $vendorRepository): JsonResponse
    {
        $categoryId = $request->query->get('category');
        $search = $request->query->get('search');
        
        $vendors = $vendorRepository->searchByNameOrCompany($search, $categoryId ? (int) $categoryId : null);

        $data = [];
        foreach ($vendors as $vendor) {
            $data[] = [
                'id' => $vendor->getId(),
                'name' => $vendor->getName(),
                'email' => $vendor->getEmail(),
                'phone' => $vendor->getPhone(),
                'companyName' => $vendor->getCompanyName(),
                'status' => $vendor->getStatus(),
                'category' => [
                    'id' => $vendor->getCategory()->getId(),
                    'name' => $vendor->getCategory()->getName()
                ]
            ];
        }

        return $this->json($data);
    }

    #[Route('/vendors/{id}', name: 'admin_vendors_show', methods: ['GET'])]
    public function showVendor(Vendor $vendor): JsonResponse
    {
        return $this->json([
            'id' => $vendor->getId(),
            'name' => $vendor->getName(),
            'email' => $vendor->getEmail(),
            'phone' => $vendor->getPhone(),
            'companyName' => $vendor->getCompanyName(),
            'address' => $vendor->getAddress(),
            'status' => $vendor->getStatus(),
            'category' => [
                'id' => $vendor->getCategory()->getId(),
                'name' => $vendor->getCategory()->getName()
            ]
        ]);
    }

    #[Route('/vendor-orders', name: 'admin_vendor_orders_list', methods: ['GET'])]
    public function listVendorOrders(Request $request, VendorOrderRepository $repository): JsonResponse
    {
        $filters = [
            'status' => $request->query->get('status'),
            'productName' => $request->query->get('productName'),
            'orderId' => $request->query->get('orderId'),
            'category' => $request->query->get('category'),
            'productId' => $request->query->get('productId'),
        ];
        
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $paginatedResponse = $repository->getPaginatedFilterOrders($filters, $page, $limit);

        $formattedData = [];
        foreach ($paginatedResponse->data as $order) {
            $formattedData[] = [
                'id' => $order->getId(),
                'vendorName' => $order->getVendor()->getName(),
                'productId' => $order->getProduct()->getId(),
                'productName' => $order->getProduct()->getName(),
                'quantity' => $order->getQuantity(),
                'status' => $order->getStatus(),
                'createdAt' => $order->getCreatedAt()->format('c'),
                'receivedAt' => $order->getReceivedAt() ? $order->getReceivedAt()->format('c') : null
            ];
        }

        return $this->json([
            'data' => $formattedData,
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/vendor-orders', name: 'admin_vendor_orders_create', methods: ['POST'])]
    public function createVendorOrder(
        Request $request,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $dto = new VendorOrderCreateDTO();
        $dto->vendorId = $data['vendorId'] ?? null;
        $dto->productId = $data['productId'] ?? null;
        $dto->quantity = $data['quantity'] ?? null;
        $dto->comment = $data['comment'] ?? null;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $vendor = $entityManager->getRepository(Vendor::class)->find($dto->vendorId);
        $product = $productRepository->find($dto->productId);

        if (!$vendor || !$product) {
            return $this->json(['message' => 'Vendor or Product not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate product belongs to vendor category
        if ($product->getCategory()->getId() !== $vendor->getCategory()->getId()) {
            return $this->json(['message' => 'Product must belong to the same category as the vendor'], Response::HTTP_BAD_REQUEST);
        }

        $order = new VendorOrder();
        $order->setVendor($vendor);
        $order->setProduct($product);
        $order->setQuantity($dto->quantity);
        $order->setComment($dto->comment);
        $order->setStatus('pending');

        $entityManager->persist($order);
        $entityManager->flush();

        return $this->json([
            'message' => 'Vendor order created successfully',
            'id' => $order->getId()
        ], Response::HTTP_CREATED);
    }

    #[Route('/vendors', name: 'admin_vendors_create', methods: ['POST'])]
    public function createVendor(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $dto = new VendorCreateDTO();
        $dto->name = $data['name'] ?? null;
        $dto->email = $data['email'] ?? null;
        $dto->phone = $data['phone'] ?? null;
        $dto->companyName = $data['companyName'] ?? null;
        $dto->address = $data['address'] ?? null;
        $dto->status = $data['status'] ?? 'active';
        $dto->categoryId = isset($data['categoryId']) ? (int)$data['categoryId'] : null;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $category = $entityManager->getRepository(Category::class)->find($dto->categoryId);
        if (!$category) {
            return $this->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $vendor = new Vendor();
        $vendor->setName($dto->name);
        $vendor->setEmail($dto->email);
        $vendor->setPhone($dto->phone);
        $vendor->setCompanyName($dto->companyName);
        $vendor->setAddress($dto->address);
        $vendor->setStatus($dto->status);
        $vendor->setCategory($category);

        $entityManager->persist($vendor);
        $entityManager->flush();

        return $this->json([
            'message' => 'Vendor created successfully',
            'id' => $vendor->getId(),
            'name' => $vendor->getName()
        ], Response::HTTP_CREATED);
    }

    #[Route('/vendor-orders/{id}/status', name: 'admin_vendor_orders_update_status', methods: ['PATCH'])]
    public function updateOrderStatus(
        VendorOrder $order,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $dto = new VendorOrderStatusUpdateDTO();
        $dto->status = $data['status'] ?? null;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Prevent updating if already received (optional, but good for stability)
        if ($order->getStatus() === 'received' && $dto->status !== 'received') {
             // Maybe allow changing if needed, but the user said "Automatic stock update logic... when changes to received"
             // If we change FROM received, we might need to decrement stock, but user didn't ask for that.
             // For now, allow it but stock logic only increases.
        }

        $order->setStatus($dto->status);

        // Handle stock increment when status changes to 'received'
        if ($dto->status === 'received' && $order->getReceivedAt() === null) {
            $product = $order->getProduct();
            if ($product) {
                $product->setStock($product->getStock() + $order->getQuantity());
                $order->setReceivedAt(new \DateTimeImmutable());
            }
        }

        $entityManager->flush();

        return $this->json(['message' => 'Order status updated successfully']);
    }

    #[Route('/vendor-orders/batch', name: 'admin_vendor_orders_batch_create', methods: ['POST'])]
    public function createBatchVendorOrder(
        Request $request,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $vendorId = $data['vendorId'] ?? null;
        $items = $data['items'] ?? [];

        if (!$vendorId || empty($items)) {
            return $this->json(['message' => 'Vendor ID and items are required'], Response::HTTP_BAD_REQUEST);
        }

        $vendor = $entityManager->getRepository(Vendor::class)->find($vendorId);
        if (!$vendor) {
            return $this->json(['message' => 'Vendor not found'], Response::HTTP_NOT_FOUND);
        }

        $orders = [];
        foreach ($items as $itemsData) {
            $productId = $itemsData['productId'] ?? null;
            $quantity = $itemsData['quantity'] ?? null;
            $comment = $itemsData['comment'] ?? null;

            if (!$productId || !$quantity) {
                return $this->json(['message' => 'Product ID and Quantity are required for all items'], Response::HTTP_BAD_REQUEST);
            }

            $product = $productRepository->find($productId);
            if (!$product) {
                return $this->json(['message' => "Product with ID $productId not found"], Response::HTTP_NOT_FOUND);
            }

            // Validate product belongs to vendor category
            if ($product->getCategory()->getId() !== $vendor->getCategory()->getId()) {
                return $this->json(['message' => "Product '{$product->getName()}' does not belong to the same category as the vendor"], Response::HTTP_BAD_REQUEST);
            }

            $order = new VendorOrder();
            $order->setVendor($vendor);
            $order->setProduct($product);
            $order->setQuantity($quantity);
            $order->setComment($comment);
            $order->setStatus('pending');

            $entityManager->persist($order);
            $orders[] = $order;
        }

        $entityManager->flush();

        return $this->json([
            'message' => count($orders) . ' vendor orders created successfully',
            'ids' => array_map(fn($o) => $o->getId(), $orders)
        ], Response::HTTP_CREATED);
    }
}