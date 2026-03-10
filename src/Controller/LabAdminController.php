<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\RegisteredCustomer;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\RegisteredCustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/labs')]
class LabAdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_lab_dashboard', methods: ['GET'])]
    public function dashboard(
        ProductRepository $productRepo,
        OrderRepository $orderRepo
    ): JsonResponse {
        $products = $productRepo->findAll();
        $totalStockValue = 0;
        $lowStockCount = 0;

        foreach ($products as $product) {
            $purchasePrice = (float)($product->getPurchasePrice() ?? 0);
            $totalStockValue += ($product->getStock() * $purchasePrice);
            if ($product->getStock() < $product->getMinimumStock()) {
                $lowStockCount++;
            }
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $todaySales = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        $recentSales = $orderRepo->findBy([], ['createdAt' => 'DESC'], 5);

        return $this->json([
            'totalProducts' => count($products),
            'stockValue' => $totalStockValue,
            'todaySales' => (float)$todaySales,
            'lowStockCount' => $lowStockCount,
            'recentSales' => array_map(fn(Order $o) => [
                'id' => $o->getId(),
                'customerName' => $o->getCustomerName(),
                'total' => (float)$o->getTotal(),
                'createdAt' => $o->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $recentSales),
        ]);
    }

    #[Route('/stock-in', name: 'admin_lab_stock_in', methods: ['POST'])]
    public function stockIn(
        Request $request,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $purchasePrice = $data['purchasePrice'] ?? null;

        if (!$productId || $quantity <= 0) {
            return $this->json(['message' => 'Invalid product or quantity'], Response::HTTP_BAD_REQUEST);
        }

        $product = $productRepo->find($productId);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $product->setStock($product->getStock() + $quantity);
        if ($purchasePrice !== null) {
            $product->setPurchasePrice((string)$purchasePrice);
        }

        $em->flush();

        return $this->json(['message' => 'Stock updated successfully', 'newQuantity' => $product->getStock()]);
    }

    #[Route('/reports', name: 'admin_lab_reports', methods: ['GET'])]
    public function reports(
        OrderRepository $orderRepo,
        ProductRepository $productRepo
    ): JsonResponse {
        // Daily Sales for the last 30 days
        $dailySales = $orderRepo->createQueryBuilder('o')
            ->select("SUBSTRING(o.createdAt, 1, 10) as date, SUM(o.total) as total")
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        // Low stock products
        $lowStockProducts = $productRepo->createQueryBuilder('p')
            ->where('p.stock < p.minimumStock')
            ->getQuery()
            ->getResult();
        
        $lowStock = array_map(fn(Product $p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'stock' => $p->getStock(),
            'minimumStock' => $p->getMinimumStock(),
            'companyName' => $p->getCompanyName(),
        ], $lowStockProducts);

        return $this->json([
            'dailySales' => $dailySales,
            'lowStock' => $lowStock
        ]);
    }

    #[Route('/invoices', name: 'admin_lab_invoices', methods: ['GET'])]
    public function listInvoices(OrderRepository $orderRepo): JsonResponse
    {
        $orders = $orderRepo->findBy([], ['createdAt' => 'DESC'], 50);
        return $this->json([
            'data' => array_map(fn(Order $o) => [
                'id' => $o->getId(),
                'customerName' => $o->getCustomerName(),
                'phone' => $o->getPhone(),
                'totalAmount' => (string)$o->getTotal(),
                'createdAt' => $o->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $orders)
        ]);
    }

    #[Route('/customers', name: 'admin_lab_customers', methods: ['GET'])]
    public function listCustomers(Request $request, RegisteredCustomerRepository $customerRepo): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        
        $filters = [
            'search' => $request->query->get('search', '')
        ];

        $paginatedResponse = $customerRepo->getPaginatedCustomers($filters, $page, $limit);

        return $this->json([
            'data' => array_map(fn(RegisteredCustomer $c) => [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'phone' => $c->getPhone(),
                'labName' => $c->getLabName(),
                'city' => $c->getCity(),
                'address' => $c->getAddress(),
                'totalSpent' => $c->getTotalSpent(),
                'remainingBalance' => $c->getRemainingBalance(),
            ], $paginatedResponse->data),
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/customers', name: 'admin_lab_customers_create', methods: ['POST'])]
    public function createCustomer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? '';
        if ($phone) {
            $existing = $em->getRepository(RegisteredCustomer::class)->findOneBy(['phone' => $phone]);
            if ($existing) {
                return $this->json(['message' => 'A customer with this phone number already exists'], Response::HTTP_CONFLICT);
            }
        }

        $customer = new RegisteredCustomer();
        $customer->setName($data['name'] ?? '');
        $customer->setPhone($phone);
        $customer->setLabName($data['labName'] ?? null);
        $customer->setCity($data['city'] ?? null);
        $customer->setAddress($data['address'] ?? null);

        $em->persist($customer);
        $em->flush();

        return $this->json(['message' => 'Customer registered successfully', 'id' => $customer->getId()], Response::HTTP_CREATED);
    }

    #[Route('/customers/{id}', name: 'admin_lab_customers_get', methods: ['GET'])]
    public function getCustomer(int $id, RegisteredCustomerRepository $customerRepo): JsonResponse
    {
        $customer = $customerRepo->find($id);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'phone' => $customer->getPhone(),
            'labName' => $customer->getLabName(),
            'city' => $customer->getCity(),
            'address' => $customer->getAddress(),
            'totalSpent' => $customer->getTotalSpent(),
            'remainingBalance' => $customer->getRemainingBalance(),
        ]);
    }

    #[Route('/customers/{id}', name: 'admin_lab_customers_update', methods: ['PUT'])]
    public function updateCustomer(int $id, Request $request, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em): JsonResponse
    {
        $customer = $customerRepo->find($id);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['phone']) && $data['phone'] !== $customer->getPhone()) {
            $existing = $customerRepo->findOneBy(['phone' => $data['phone']]);
            if ($existing) {
                return $this->json(['message' => 'This phone number is already registered to another customer'], Response::HTTP_CONFLICT);
            }
            $customer->setPhone($data['phone']);
        }
        if (isset($data['name'])) $customer->setName($data['name']);
        if (isset($data['labName'])) $customer->setLabName($data['labName']);
        if (isset($data['city'])) $customer->setCity($data['city']);
        if (isset($data['address'])) $customer->setAddress($data['address']);

        $em->flush();

        return $this->json(['message' => 'Customer updated successfully']);
    }

    #[Route('/customers/{id}', name: 'admin_lab_customers_delete', methods: ['DELETE'])]
    public function deleteCustomer(int $id, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em): JsonResponse
    {
        $customer = $customerRepo->find($id);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($customer);
        $em->flush();

        return $this->json(['message' => 'Customer deleted successfully']);
    }
}