<?php

namespace App\Controller;

use App\Entity\CashRecovery;
use App\Entity\RegisteredCustomer;
use App\Entity\Order;
use App\Repository\RegisteredCustomerRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class CashRecoveryController extends AbstractController
{
    #[Route('/cash-recovery/customers', name: 'admin_cash_recovery_customers', methods: ['GET'])]
    public function listCustomers(
        Request $request,
        RegisteredCustomerRepository $customerRepo,
        TenantContext $tenantContext
    ): JsonResponse {
        $companyId = $tenantContext->getCurrentCompanyId();
        
        if (!$companyId) {
            return $this->json(['message' => 'Company context not found'], Response::HTTP_BAD_REQUEST);
        }

        $filters = [
            'search' => $request->query->get('search'),
            'pending' => $request->query->get('pending', 'true'),
        ];
        
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $paginatedResponse = $customerRepo->getPaginatedCustomers($filters, (int)$companyId, $page, $limit);

        // Map entities to plain arrays to avoid serialization issues (like circular references)
        $customers = array_map(function(RegisteredCustomer $c) {
            return [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'phone' => $c->getPhone(),
                'labName' => $c->getLabName(),
                'remainingBalance' => round($c->getRemainingBalance()),
                'totalSpent' => round($c->getTotalSpent()),
            ];
        }, $paginatedResponse->data);

        return $this->json([
            'data' => $customers,
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'totalPages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('/cash-recovery/pay', name: 'admin_cash_recovery_pay', methods: ['POST'])]
    public function recoverCash(
        Request $request,
        EntityManagerInterface $em,
        RegisteredCustomerRepository $customerRepo,
        TenantContext $tenantContext
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $customerId = $data['customerId'] ?? null;
        $amount = (float) ($data['amount'] ?? 0);
        $remarks = $data['remarks'] ?? null;

        if (!$customerId || $amount <= 0) {
            return $this->json(['message' => 'Invalid customer or amount'], Response::HTTP_BAD_REQUEST);
        }

        $companyId = $tenantContext->getCurrentCompanyId();
        $customer = $customerRepo->findOneBy(['id' => $customerId, 'company' => $companyId]);

        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        if ($amount > $customer->getRemainingBalance()) {
            return $this->json(['message' => 'Payment exceeds pending balance. Maximum allowed: PKR ' . $customer->getRemainingBalance()], Response::HTTP_BAD_REQUEST);
        }

        $recovery = new CashRecovery();
        $recovery->setAmount($amount);
        $recovery->setRegisteredCustomer($customer);
        $recovery->setRemarks($remarks);
        $recovery->setCompany($customer->getCompany());
        $recovery->setUser($this->getUser());

        $customer->setRemainingBalance($customer->getRemainingBalance() - $amount);

        // Distribute payment to oldest unpaid orders to keep historical reports accurate
        $paymentRemaining = $amount;
        $unpaidOrders = $em->getRepository(Order::class)->createQueryBuilder('o')
            ->where('o.registeredCustomer = :customer')
            ->andWhere('o.changeDue < 0')
            ->setParameter('customer', $customer)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($unpaidOrders as $order) {
            if ($paymentRemaining <= 0) break;

            $orderPending = abs($order->getChangeDue());
            if ($paymentRemaining >= $orderPending) {
                $order->setChangeDue(0);
                $order->setAmountTendered($order->getAmountTendered() + $orderPending);
                $order->setPaidAt(new \DateTime());
                $paymentRemaining -= $orderPending;
            } else {
                $order->setChangeDue($order->getChangeDue() + $paymentRemaining);
                $order->setAmountTendered($order->getAmountTendered() + $paymentRemaining);
                $order->setPaidAt(new \DateTime());
                $paymentRemaining = 0;
            }
        }

        $em->persist($recovery);
        $em->flush();

        return $this->json([
            'message' => 'Payment recorded successfully',
            'recoveryId' => $recovery->getId(),
            'newBalance' => $customer->getRemainingBalance()
        ], Response::HTTP_CREATED);
    }
}
