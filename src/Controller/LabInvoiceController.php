<?php

namespace App\Controller;

use App\Entity\RegisteredCustomer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\VendorOrder;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Entity\LabExpense;
use App\Entity\CashRecovery;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/labs/invoice')]
class LabInvoiceController extends AbstractController
{
    #[Route('/expense/{id}', name: 'admin_lab_invoice_expense', methods: ['GET'])]
    public function expenseInvoice(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $expense = $entityManager->getRepository(LabExpense::class)->find($id);
        if (!$expense) {
            return $this->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        $html = $this->renderView('invoice/expense_invoice.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'logo' => $this->getLogoData(),
            'documentTitle' => 'Expense Receipt',
            'documentNumber' => 'EXP-' . str_pad($expense->getId(), 6, '0', STR_PAD_LEFT),
            'date' => new \DateTime(),
            'expense' => $expense,
            'companyName' => $expense->getCompany() ? $expense->getCompany()->getName() : 'Unknown Company',
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        return $this->generatePdfResponse($html, sprintf('Expense-%s.pdf', $expense->getId()));
    }

    #[Route('/reagent/{id}', name: 'admin_lab_invoice_reagent', methods: ['GET'])]
    public function reagentInvoice(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Reagent not found'], Response::HTTP_NOT_FOUND);
        }

        $html = $this->renderView('invoice/inventory_invoice.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'type' => 'reagent',
            'logo' => $this->getLogoData(),
            'isEditing' => true, // Assuming if we are here, it's either new or edit, we can use a generic term
            'documentTitle' => 'Reagent Inventory Record',
            'documentNumber' => 'REG-' . str_pad($product->getId(), 5, '0', STR_PAD_LEFT),
            'date' => new \DateTime(),
            'reagent' => [
                'name' => $product->getName(),
                'batchNumber' => $product->getBatchNumber(),
                'categoryName' => $product->getCategory() ? $product->getCategory()->getName() : 'Uncategorized',
                'expiryDate' => $product->getExpiryDate() ? $product->getExpiryDate()->format('d M Y') : 'N/A',
                'packSize' => $product->getPackSize(),
                'stock' => $product->getStock(),
                'price' => $product->getPrice()
            ],
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        return $this->generatePdfResponse($html, sprintf('Reagent-%s.pdf', $product->getId()));
    }

    #[Route('/stock-in', name: 'admin_lab_invoice_stock_in', methods: ['GET'])]
    public function stockInInvoice(Request $request, EntityManagerInterface $entityManager): Response
    {
        $productId = $request->query->get('productId');
        $quantity = $request->query->get('quantity');
        $unitPrice = $request->query->get('unitPrice');
        $supplier = $request->query->get('supplier', 'N/A');

        if (!$productId || !$quantity) {
            return $this->json(['message' => 'Missing required information'], Response::HTTP_BAD_REQUEST);
        }

        $product = $entityManager->getRepository(Product::class)->find($productId);
        $productName = $product ? $product->getName() : 'Unknown Product';

        $html = $this->renderView('invoice/inventory_invoice.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'type' => 'stock_in',
            'logo' => $this->getLogoData(),
            'documentTitle' => 'Stock Entry Receipt',
            'documentNumber' => 'STK-' . date('Ymd') . '-' . rand(100, 999),
            'date' => new \DateTime(),
            'stockIn' => [
                'productName' => $productName,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'supplier' => $supplier
            ],
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        return $this->generatePdfResponse($html, 'Stock-Entry.pdf');
    }

    #[Route('/customer/{id}', name: 'admin_lab_invoice_customer_statement', methods: ['GET'])]
    public function customerStatement(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = $entityManager->getRepository(RegisteredCustomer::class)->find($id);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $period = $request->query->get('period', 'monthly'); // monthly or yearly
        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        $qb = $entityManager->getRepository(Order::class)->createQueryBuilder('o')
            ->where('o.registeredCustomer = :customer')
            ->setParameter('customer', $customer);

        if ($period === 'monthly') {
            $startDate = new \DateTime(sprintf('%d-%d-01 00:00:00', $year, $month));
            $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);
            $qb->andWhere('o.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate);
            $periodLabel = $startDate->format('F Y');
        } elseif ($period === 'yearly') {
            $startDate = new \DateTime(sprintf('%d-01-01 00:00:00', $year));
            $endDate = new \DateTime(sprintf('%d-12-31 23:59:59', $year));
            $qb->andWhere('o.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate);
            $periodLabel = (string) $year;
        } else {
            $periodLabel = 'All Time';
        }

        $orders = $qb->orderBy('o.createdAt', 'ASC')->getQuery()->getResult();

        // Fetch Cash Recoveries
        $crQb = $entityManager->getRepository(CashRecovery::class)->createQueryBuilder('cr')
            ->where('cr.registeredCustomer = :customer')
            ->setParameter('customer', $customer);

        if ($period === 'monthly' || $period === 'yearly') {
            $crQb->andWhere('cr.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate);
        }

        $recoveries = $crQb->orderBy('cr.createdAt', 'ASC')->getQuery()->getResult();

        $statementItems = [];
        $totalSpent = 0;
        $totalDiscount = 0;
        $totalPaid = 0;

        foreach ($orders as $order) {
            $totalSpent += $order->getTotal();
            $totalPaid += $order->getAmountTendered() ?: 0;
            $totalDiscount += ($order->getDiscountAmount() ?: 0);

            $itemsData = [];
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                $itemsData[] = [
                    'name' => $product ? $product->getName() : 'Unknown Product',
                    'description' => $product ? $product->getDescription() : '',
                    'quantity' => $item->getQuantity(),
                    'price' => round((float) $item->getPrice()),
                    'discountPercentage' => round((float) $item->getDiscountPercentage()),
                    'discountAmount' => round((float) $item->getDiscountAmount()),
                    'total' => round((float) ($item->getQuantity() * $item->getPrice() - ($item->getDiscountAmount() ?: 0)))
                ];
            }

            $pendingAmount = $order->getChangeDue() < 0 ? round(abs($order->getChangeDue())) : 0;

            $statementItems[] = [
                'type' => 'order',
                'date' => $order->getCreatedAt(),
                'orderId' => $order->getId(),
                'items' => $itemsData,
                'total' => round($order->getTotal()),
                'discountPercentage' => round($order->getDiscountPercentage() ?: 0),
                'discountAmount' => round($order->getDiscountAmount() ?: 0),
                'amountTendered' => round($order->getAmountTendered() ?: 0),
                'pending' => $pendingAmount,
                'remarks' => $order->getRemarks(),
                'paidAt' => $order->getPaidAt() ?: ($order->getAmountTendered() > 0 ? $order->getCreatedAt() : null)
            ];
        }

        foreach ($recoveries as $recovery) {
            $totalPaid += $recovery->getAmount();
            $statementItems[] = [
                'type' => 'recovery',
                'date' => $recovery->getCreatedAt(),
                'recoveryId' => $recovery->getId(),
                'amount' => round($recovery->getAmount()),
                'remarks' => $recovery->getRemarks() ?: 'Cash Recovery Payment',
                'paidAt' => $recovery->getCreatedAt()
            ];
        }

        // Sort everything by date
        usort($statementItems, function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        // Convert dates to strings for template compatibility
        foreach ($statementItems as &$item) {
            $item['date'] = $item['date']->format('Y-m-d');
            if ($item['paidAt']) $item['paidAt'] = $item['paidAt']->format('Y-m-d');
        }

        $company = $customer->getCompany();
        $settings = $company ? $company->getSettingsJson() : [];

        $companyData = [
            'name' => $company ? $company->getName() : 'Unique Healthcare Solutions',
            'phone' => $settings['phone'] ?? 'N/A',
            'address' => $settings['address'] ?? 'N/A',
        ];

        $html = $this->renderView('invoice/customer_statement.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'logo' => $this->getLogoData(),
            'company' => $companyData,
            'customer' => [
                'name' => $customer->getName(),
                'phone' => $customer->getPhone(),
                'labName' => $customer->getLabName(),
                'city' => $customer->getCity(),
                'address' => $customer->getAddress()
            ],
            'customerRemainingBalance' => round($customer->getRemainingBalance()),
            'periodLabel' => $periodLabel,
            'orders' => $statementItems,
            'totalSpent' => round($totalSpent),
            'totalPaid' => round($totalPaid),
            'subTotal' => round($totalSpent + $totalDiscount),
            'totalDiscount' => round($totalDiscount),
            'statementNumber' => 'STMT-' . date('Ymd') . '-' . str_pad($customer->getId(), 4, '0', STR_PAD_LEFT),
            'date' => new \DateTime(),
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        $disposition = $request->query->getBoolean('preview', false) ? 'inline' : 'attachment';
        return $this->generatePdfResponse($html, sprintf('Statement-%s-%s.pdf', $customer->getName(), $periodLabel), $disposition);
    }

    #[Route('/download', name: 'admin_lab_invoice_download', methods: ['GET'])]
    public function downloadOrder(Request $request, EntityManagerInterface $entityManager): Response
    {
        $orderId = $request->query->get('orderId');
        if (!$orderId) {
            return $this->json(['message' => 'Missing order ID'], Response::HTTP_BAD_REQUEST);
        }

        $order = $entityManager->getRepository(Order::class)->find($orderId);
        if (!$order) {
            return $this->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $html = $this->renderView('invoice/order_invoice.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'logo' => $this->getLogoData(),
            'order' => $order,
            'date' => new \DateTime(),
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        $disposition = $request->query->getBoolean('preview', false) ? 'inline' : 'attachment';
        return $this->generatePdfResponse($html, sprintf('Invoice-%s.pdf', $order->getId()), $disposition);
    }

    #[Route('/recovery/{id}', name: 'admin_lab_invoice_recovery', methods: ['GET'])]
    public function recoveryReceipt(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $recovery = $entityManager->getRepository(CashRecovery::class)->find($id);
        if (!$recovery) {
            return $this->json(['message' => 'Recovery record not found'], Response::HTTP_NOT_FOUND);
        }

        $html = $this->renderView('invoice/cash_recovery_invoice.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'logo' => $this->getLogoData(),
            'recovery' => $recovery,
            'date' => new \DateTime(),
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        $disposition = $request->query->getBoolean('preview', false) ? 'inline' : 'attachment';
        return $this->generatePdfResponse($html, sprintf('Recovery-%s.pdf', $recovery->getId()), $disposition);
    }

    #[Route('/summary', name: 'admin_lab_invoice_summary', methods: ['GET'])]
    public function summaryReport(Request $request, EntityManagerInterface $em, TenantContext $tenantContext): Response
    {
        $type = $request->query->get('type', 'daily');
        $dateStr = $request->query->get('date', date('Y-m-d'));
        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('n'));

        $startDate = new \DateTime();
        $endDate = new \DateTime();

        if ($type === 'daily') {
            $startDate = new \DateTime($dateStr . ' 00:00:00');
            $endDate = (clone $startDate)->setTime(23, 59, 59);
            $periodLabel = $startDate->format('d M Y');
        } elseif ($type === 'monthly') {
            $startDate = new \DateTime(sprintf('%d-%d-01 00:00:00', $year, $month));
            $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);
            $periodLabel = $startDate->format('F Y');
        } else { // yearly
            $startDate = new \DateTime(sprintf('%d-01-01 00:00:00', $year));
            $endDate = new \DateTime(sprintf('%d-12-31 23:59:59', $year));
            $periodLabel = (string) $year;
        }

        // 1. Revenue & Customers from Orders in Period
        $orderData = $em->getRepository(Order::class)->createQueryBuilder('o')
            ->select('SUM(o.total) as total, COUNT(o.id) as customerCount')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $tenantContext->getCurrentCompanyId())
            ->getQuery()
            ->getOneOrNullResult();

        $revenue = round((float) ($orderData['total'] ?? 0));
        $customerCount = (int) ($orderData['customerCount'] ?? 0);

        // 2. Total Pending: Sum of all RegisteredCustomer balances (Global)
        $totalPending = round((float) $em->getRepository(RegisteredCustomer::class)->createQueryBuilder('rc')
            ->select('SUM(rc.remainingBalance)')
            ->where('rc.company = :company')
            ->setParameter('company', $tenantContext->getCurrentCompanyId())
            ->getQuery()
            ->getSingleScalarResult());

        $receivedPayments = $revenue - $totalPending; 

        // 2. Expenses & Stock Added (Vendor Orders received)
        $vendorOrders = $em->getRepository(VendorOrder::class)->createQueryBuilder('vo')
            ->where('vo.status = :status')
            ->andWhere('vo.receivedAt BETWEEN :start AND :end')
            ->andWhere('vo.company = :company')
            ->setParameter('status', 'received')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $tenantContext->getCurrentCompanyId())
            ->getQuery()
            ->getResult();

        $expenses = 0;
        $stockAdded = 0;
        foreach ($vendorOrders as $vo) {
            $stockAdded += $vo->getQuantity();
            $purchasePrice = (float) ($vo->getProduct() ? $vo->getProduct()->getPurchasePrice() : 0);
            $expenses += ($vo->getQuantity() * $purchasePrice);
        }
        $expenses = round($expenses);

        // 2b. Lab Expenses
        $labExpensesResult = $em->getRepository(LabExpense::class)->createQueryBuilder('le')
            ->select('SUM(le.amount)')
            ->where('le.expenseDate BETWEEN :start AND :end')
            ->andWhere('le.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $tenantContext->getCurrentCompanyId())
            ->getQuery()
            ->getSingleScalarResult();

        $labExpenses = round((float) ($labExpensesResult ?? 0));

        // 3. Stock Removed (Order Items)
        $stockRemoved = $em->getRepository(OrderItem::class)->createQueryBuilder('oi')
            ->join('oi.order', 'o')
            ->select('SUM(oi.quantity)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $tenantContext->getCurrentCompanyId())
            ->getQuery()
            ->getSingleScalarResult();
        $stockRemoved = (int) ($stockRemoved ?? 0);

        $html = $this->renderView('invoice/summary_report.html.twig', [
            'showHeader' => $request->query->getBoolean('showHeader', true),
            'logo' => $this->getLogoData(),
            'periodLabel' => $periodLabel,
            'reportType' => ucfirst($type),
            'revenue' => $revenue,
            'expenses' => $expenses,
            'labExpenses' => $labExpenses,
            'netIncome' => $receivedPayments - ($expenses + $labExpenses),
            'stockAdded' => $stockAdded,
            'stockRemoved' => $stockRemoved,
            'customerCount' => $customerCount,
            'totalPending' => $totalPending,
            'dateGenerated' => new \DateTime(),
            'invoiceMaker' => ($this->getUser() instanceof User) ? $this->getUser()->getName() : 'System'
        ]);

        return $this->generatePdfResponse($html, sprintf('Lab-Summary-%s-%s.pdf', ucfirst($type), str_replace(' ', '-', $periodLabel)));
    }

    private function generatePdfResponse(string $html, string $filename, string $disposition = 'attachment'): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
        ]);
    }

    private function getLogoData(): string
    {
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        if (file_exists($logoPath)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        return '';
    }
}