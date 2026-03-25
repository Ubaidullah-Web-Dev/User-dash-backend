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
use App\Service\TenantContext;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

#[Route('/api/admin/labs')]
class LabAdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_lab_dashboard', methods: ['GET'])]
    public function dashboard(
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        TenantContext $tenantContext
    ): JsonResponse {
        $companyId = $tenantContext->getCurrentCompanyId();
        $products = $productRepo->findBy(['company' => $companyId]);
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
            ->andWhere('o.company = :companyId')
            ->setParameter('today', $today)
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getSingleScalarResult();

        $recentSales = $orderRepo->findBy(['company' => $companyId], ['createdAt' => 'DESC'], 5);

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
        EntityManagerInterface $em,
        TenantContext $tenantContext
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        $quantity = $data['quantity'] ?? 0;
        $purchasePrice = $data['purchasePrice'] ?? null;
        $supplier = $data['supplier'] ?? null;

        if (!$productId || $quantity <= 0) {
            return $this->json(['message' => 'Invalid product or quantity'], Response::HTTP_BAD_REQUEST);
        }

        $product = $productRepo->findOneBy(['id' => $productId, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$product) {
            return $this->json(['message' => 'Product not found or access denied'], Response::HTTP_NOT_FOUND);
        }

        $product->setStock($product->getStock() + $quantity);
        if ($purchasePrice !== null) {
            $product->setPurchasePrice((string)$purchasePrice);
        }
        if ($supplier) {
            $product->setCompanyName($supplier);
        }

        $em->flush();

        return $this->json(['message' => 'Stock updated successfully', 'newQuantity' => $product->getStock()]);
    }

    #[Route('/reports', name: 'admin_lab_reports', methods: ['GET'])]
    public function reports(
        OrderRepository $orderRepo,
        ProductRepository $productRepo,
        TenantContext $tenantContext
    ): JsonResponse {
        $companyId = $tenantContext->getCurrentCompanyId();

        // Daily Sales for the last 30 days
        $dailySales = $orderRepo->createQueryBuilder('o')
            ->select("SUBSTRING(o.createdAt, 1, 10) as date, SUM(o.total) as total, SUM(CASE WHEN o.changeDue < 0 THEN ABS(o.changeDue) ELSE 0 END) as pending")
            ->where('o.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        // Low stock products
        $lowStockProducts = $productRepo->createQueryBuilder('p')
            ->where('p.stock < p.minimumStock')
            ->andWhere('p.company = :companyId')
            ->setParameter('companyId', $companyId)
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
    public function listInvoices(Request $request, OrderRepository $orderRepo, TenantContext $tenantContext): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $search = $request->query->get('search', '');

        $paginatedResponse = $orderRepo->getPaginatedOrders(['search' => $search], $tenantContext->getCurrentCompanyId(), $page, $limit);

        return $this->json([
            'data' => array_map(fn(Order $o) => [
                'id' => $o->getId(),
                'customerName' => $o->getCustomerName(),
                'phone' => $o->getPhone(),
                'totalAmount' => (string)$o->getTotal(),
                'pendingAmount' => $o->getChangeDue() < 0 ? abs($o->getChangeDue()) : 0,
                'createdAt' => $o->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $paginatedResponse->data),
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/customers', name: 'admin_lab_customers', methods: ['GET'])]
    public function listCustomers(Request $request, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        
        $filters = [
            'search' => $request->query->get('search', ''),
            'pending' => $request->query->get('pending')
        ];

        $paginatedResponse = $customerRepo->getPaginatedCustomers($filters, $tenantContext->getCurrentCompanyId(), $page, $limit);

        // Calculate total discount for each customer from their orders
        $customerData = array_map(function(RegisteredCustomer $c) use ($em) {
            $totalDiscount = $em->getRepository(Order::class)->createQueryBuilder('o')
                ->select('SUM(o.discountAmount)')
                ->where('o.registeredCustomer = :customer')
                ->setParameter('customer', $c)
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'phone' => $c->getPhone(),
                'labName' => $c->getLabName(),
                'city' => $c->getCity(),
                'address' => $c->getAddress(),
                'totalSpent' => $c->getTotalSpent(),
                'totalDiscount' => (float)($totalDiscount ?? 0),
                'remainingBalance' => $c->getRemainingBalance(),
            ];
        }, $paginatedResponse->data);

        return $this->json([
            'data' => $customerData,
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/customers', name: 'admin_lab_customers_create', methods: ['POST'])]
    public function createCustomer(Request $request, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? '';
        if ($phone) {
            $existing = $em->getRepository(RegisteredCustomer::class)->findOneBy([
                'phone' => $phone,
                'company' => $tenantContext->getCurrentCompanyId()
            ]);
            if ($existing) {
                return $this->json(['message' => 'A customer with this phone number already registered for your company'], Response::HTTP_CONFLICT);
            }
        }

        $customer = new RegisteredCustomer();
        $customer->setCompany($tenantContext->getCurrentCompany());
        $customer->setName($data['name'] ?? '');
        $customer->setPhone($phone);
        $customer->setLabName($data['labName'] ?? null);
        $customer->setCity($data['city'] ?? null);
        $customer->setAddress($data['address'] ?? null);

        try {
            $em->persist($customer);
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
             return $this->json(['message' => 'Phone number already in use.'], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
             return $this->json(['message' => 'Failed to save customer record.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['message' => 'Customer registered successfully', 'id' => $customer->getId()], Response::HTTP_CREATED);
    }

    #[Route('/customers/{id}', name: 'admin_lab_customers_get', methods: ['GET'])]
    public function getCustomer(int $id, RegisteredCustomerRepository $customerRepo, TenantContext $tenantContext): JsonResponse
    {
        $customer = $customerRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found or access denied'], Response::HTTP_NOT_FOUND);
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
    public function updateCustomer(int $id, Request $request, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $customer = $customerRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found or access denied'], Response::HTTP_NOT_FOUND);
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
        if (isset($data['remainingBalance'])) $customer->setRemainingBalance((float)$data['remainingBalance']);

        $em->flush();

        return $this->json(['message' => 'Customer updated successfully']);
    }

    #[Route('/customers/{id}', name: 'admin_lab_customers_delete', methods: ['DELETE'])]
    public function deleteCustomer(int $id, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $customer = $customerRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found or access denied'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($customer);
        $em->flush();

        return $this->json(['message' => 'Customer deleted successfully']);
    }

    #[Route('/customers/{id}/adjust-balance', name: 'admin_lab_customers_adjust_balance', methods: ['POST'])]
    public function adjustCustomerBalance(int $id, Request $request, RegisteredCustomerRepository $customerRepo, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $customer = $customerRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found or access denied'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $amount = (float)($data['amount'] ?? 0);
        $action = $data['action'] ?? 'add'; // 'add' or 'subtract'

        if ($amount < 0) {
            return $this->json(['message' => 'Amount must be positive'], Response::HTTP_BAD_REQUEST);
        }

        if ($action === 'subtract') {
            $customer->setRemainingBalance($customer->getRemainingBalance() - $amount);
        } else {
            $customer->setRemainingBalance($customer->getRemainingBalance() + $amount);
        }

        $em->flush();

        return $this->json([
            'message' => 'Balance adjusted successfully',
            'newBalance' => $customer->getRemainingBalance()
        ]);
    }
}