<?php

namespace App\Controller;

use App\Entity\LabExpense;
use App\Repository\LabExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\TenantContext;

#[Route('/api/admin/labs/expenses')]
class LabExpenseController extends AbstractController
{
    #[Route('', name: 'admin_lab_expenses_list', methods: ['GET'])]
    public function listExpenses(Request $request, LabExpenseRepository $expenseRepo, TenantContext $tenantContext): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $search = $request->query->get('search', '');
        $startDate = $request->query->get('startDate', '');
        $endDate = $request->query->get('endDate', '');

        $filters = [
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $paginatedResponse = $expenseRepo->getPaginatedExpenses($filters, $tenantContext->getCurrentCompanyId(), $page, $limit);

        return $this->json([
            'data' => array_map(fn(LabExpense $e) => [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'description' => $e->getDescription(),
                'amount' => (string)$e->getAmount(),
                'expenseDate' => $e->getExpenseDate()->format('Y-m-d'),
                'category' => $e->getCategory(),
                'createdAt' => $e->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $paginatedResponse->data),
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'limit' => $paginatedResponse->limit,
            'pages' => $paginatedResponse->pages,
        ]);
    }

    #[Route('', name: 'admin_lab_expenses_create', methods: ['POST'])]
    public function createExpense(Request $request, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || !isset($data['amount'])) {
            return $this->json(['message' => 'Title and amount are required'], Response::HTTP_BAD_REQUEST);
        }

        $expense = new LabExpense();
        $expense->setCompany($tenantContext->getCurrentCompany());
        $expense->setTitle($data['title']);
        $expense->setDescription($data['description'] ?? null);
        $expense->setAmount((string)$data['amount']);
        
        $dateStr = $data['expenseDate'] ?? date('Y-m-d');
        $expense->setExpenseDate(new \DateTimeImmutable($dateStr));
        $expense->setCategory($data['category'] ?? null);

        $em->persist($expense);
        $em->flush();

        return $this->json([
            'message' => 'Expense created successfully',
            'id' => $expense->getId()
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_lab_expenses_get', methods: ['GET'])]
    public function getExpense(int $id, LabExpenseRepository $expenseRepo, TenantContext $tenantContext): JsonResponse
    {
        $expense = $expenseRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$expense) {
            return $this->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $expense->getId(),
            'title' => $expense->getTitle(),
            'description' => $expense->getDescription(),
            'amount' => (string)$expense->getAmount(),
            'expenseDate' => $expense->getExpenseDate()->format('Y-m-d'),
            'category' => $expense->getCategory(),
            'createdAt' => $expense->getCreatedAt()->format('Y-m-d H:i:s'),
            'companyName' => $expense->getCompany()->getName()
        ]);
    }

    #[Route('/{id}', name: 'admin_lab_expenses_delete', methods: ['DELETE'])]
    public function deleteExpense(int $id, LabExpenseRepository $expenseRepo, EntityManagerInterface $em, TenantContext $tenantContext): JsonResponse
    {
        $expense = $expenseRepo->findOneBy(['id' => $id, 'company' => $tenantContext->getCurrentCompanyId()]);
        if (!$expense) {
            return $this->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($expense);
        $em->flush();

        return $this->json(['message' => 'Expense deleted successfully']);
    }
}
