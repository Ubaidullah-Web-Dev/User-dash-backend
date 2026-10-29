<?php

namespace App\Tests\Controller;

use App\Controller\LabInvoiceController;
use App\Entity\Company;
use App\Entity\User;
use App\Service\FinancialCalculatorService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class RandomDateRangeAuditTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TenantContext $tenantContext;
    private FinancialCalculatorService $calculator;
    private LabInvoiceController $controller;
    private Company $company;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->tenantContext = new TenantContext($this->em);
        $this->calculator = new FinancialCalculatorService();

        $company = $this->em->getRepository(Company::class)->find(1);
        $this->assertNotNull($company);
        $this->company = $company;
        $this->tenantContext->setCurrentCompany($this->company);

        $this->controller = new LabInvoiceController();
        $this->controller->setContainer($container);
    }

    public static function randomDateRangeProvider(): array
    {
        $cases = [];

        // 1. Specific milestone dates & ranges
        $milestones = [
            // Daily tests on key active dates
            ['daily', '2026-05-08', '2026-05-08'],
            ['daily', '2026-05-21', '2026-05-21'],
            ['daily', '2026-06-01', '2026-06-01'],
            ['daily', '2026-06-11', '2026-06-11'],
            ['daily', '2026-07-09', '2026-07-09'],
            ['daily', '2026-07-20', '2026-07-20'], // Order #96 (TSH GP pack item)
            ['daily', '2026-07-28', '2026-07-28'],
            ['daily', '2026-08-03', '2026-08-03'],
            ['daily', '2026-08-11', '2026-08-11'],
            ['daily', '2026-08-24', '2026-08-24'],
            ['daily', '2026-08-26', '2026-08-26'],
            ['daily', '2026-09-01', '2026-09-01'],
            ['daily', '2026-09-07', '2026-09-07'],
            ['daily', '2026-09-11', '2026-09-11'],
            ['daily', '2026-09-21', '2026-09-21'],
            ['daily', '2026-09-25', '2026-09-25'],
            ['daily', '2026-09-29', '2026-09-29'],
            ['daily', '2026-09-30', '2026-09-30'],
            ['daily', '2026-10-01', '2026-10-01'],
            ['daily', '2026-10-10', '2026-10-10'], // Order #310 (TSH GP pack item)
            ['daily', '2026-10-12', '2026-10-12'],
            ['daily', '2026-10-13', '2026-10-13'], // High volume day (many orders + recoveries)
            ['daily', '2026-10-14', '2026-10-14'],

            // Full Month ranges
            ['custom', '2026-05-01', '2026-05-31'],
            ['custom', '2026-06-01', '2026-06-30'],
            ['custom', '2026-07-01', '2026-07-31'],
            ['custom', '2026-08-01', '2026-08-31'],
            ['custom', '2026-09-01', '2026-09-30'],
            ['custom', '2026-10-01', '2026-10-15'],

            // Fortnights & Bi-weekly ranges
            ['custom', '2026-05-01', '2026-05-15'],
            ['custom', '2026-05-16', '2026-05-31'],
            ['custom', '2026-06-01', '2026-06-15'],
            ['custom', '2026-06-16', '2026-06-30'],
            ['custom', '2026-07-01', '2026-07-15'],
            ['custom', '2026-07-16', '2026-07-31'],
            ['custom', '2026-08-01', '2026-08-15'],
            ['custom', '2026-08-16', '2026-08-31'],
            ['custom', '2026-09-01', '2026-09-15'],
            ['custom', '2026-09-16', '2026-09-30'],
            ['custom', '2026-10-01', '2026-10-07'],
            ['custom', '2026-10-08', '2026-10-15'],

            // Month boundary spans
            ['custom', '2026-05-25', '2026-06-05'],
            ['custom', '2026-06-25', '2026-07-05'],
            ['custom', '2026-07-25', '2026-08-05'],
            ['custom', '2026-08-25', '2026-09-05'],
            ['custom', '2026-09-25', '2026-10-05'],

            // Empty dates (zero activity boundary testing)
            ['daily', '2026-01-01', '2026-01-01'],
            ['custom', '2026-01-01', '2026-01-15'],
            ['custom', '2025-06-01', '2025-06-30'],
            ['custom', '2027-01-01', '2027-01-31'],
        ];

        foreach ($milestones as $m) {
            $cases["Range_{$m[0]}_{$m[1]}_to_{$m[2]}"] = [$m[0], $m[1], $m[2]];
        }

        // 2. Generate deterministic pseudo-random ranges to reach 100 total tests
        // Seeded so tests are reproducible
        mt_srand(42);
        $minTimestamp = strtotime('2026-05-08');
        $maxTimestamp = strtotime('2026-10-15');

        while (count($cases) < 100) {
            $t1 = mt_rand($minTimestamp, $maxTimestamp);
            $t2 = mt_rand($minTimestamp, $maxTimestamp);
            if ($t1 > $t2) {
                [$t1, $t2] = [$t2, $t1];
            }

            $sDate = date('Y-m-d', $t1);
            $eDate = date('Y-m-d', $t2);
            $type = ($sDate === $eDate && mt_rand(0, 1) === 1) ? 'daily' : 'custom';

            $key = sprintf("Random_%03d_%s_%s_to_%s", count($cases) + 1, $type, $sDate, $eDate);
            if (!isset($cases[$key])) {
                $cases[$key] = [$type, $sDate, $eDate];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider randomDateRangeProvider
     */
    public function testSummaryReportAuditAcrossDateRange(string $type, string $startStr, string $endStr): void
    {
        $range = $this->calculator->resolveDateRange(
            $type,
            $startStr,
            null,
            null,
            $startStr,
            $endStr
        );

        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        // 1. Query Orders in that range
        $orders = $this->em->getRepository(\App\Entity\Order::class)->createQueryBuilder('o')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $this->company->getId())
            ->getQuery()
            ->getResult();

        $expectedBilledRevenue = 0.0;
        $expectedDiscountsGiven = 0.0;
        $expectedPending = 0.0;
        $expectedPosCashTendered = 0.0;

        foreach ($orders as $order) {
            $orderTotal = (float) $order->getTotal();
            $orderTendered = (float) ($order->getAmountTendered() ?? 0.0);
            $orderDiscount = (float) ($order->getDiscountAmount() ?? 0.0);
            $orderChangeDue = (float) ($order->getChangeDue() ?? 0.0);

            // Verify order math
            $calcItemsTotal = 0.0;
            $calcItemsDiscount = 0.0;
            foreach ($order->getItems() as $item) {
                $sub = $item->getQuantity() * (float)$item->getPrice();
                $disc = (float)($item->getDiscountAmount() ?? 0.0);
                $calcItemsTotal += ($sub - $disc);
                $calcItemsDiscount += $disc;
            }

            $this->assertEqualsWithDelta($orderTotal, $calcItemsTotal, 0.05, "Order #{$order->getId()} total must match item totals");
            $this->assertEqualsWithDelta($orderDiscount, $calcItemsDiscount, 0.05, "Order #{$order->getId()} discount must match item discounts");
            $this->assertEqualsWithDelta($orderChangeDue, $orderTendered - $orderTotal, 0.05, "Order #{$order->getId()} changeDue must match (tendered - total)");

            $expectedBilledRevenue += $orderTotal;
            $expectedDiscountsGiven += $orderDiscount;
            $expectedPosCashTendered += $orderTendered;
            if ($orderChangeDue < 0.0) {
                $expectedPending += abs($orderChangeDue);
            }
        }

        // 2. Query Recoveries in that range
        $recoveries = $this->em->getRepository(\App\Entity\CashRecovery::class)->createQueryBuilder('cr')
            ->where('cr.createdAt BETWEEN :start AND :end')
            ->andWhere('cr.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $this->company->getId())
            ->getQuery()
            ->getResult();

        $expectedRecoveries = 0.0;
        foreach ($recoveries as $cr) {
            $expectedRecoveries += (float) $cr->getAmount();
        }

        // 3. Query Order Items & COGS
        $orderItems = $this->em->getRepository(\App\Entity\OrderItem::class)->createQueryBuilder('oi')
            ->join('oi.order', 'o')
            ->leftJoin('oi.product', 'p')
            ->addSelect('p')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $this->company->getId())
            ->getQuery()
            ->getResult();

        $expectedCogs = $this->calculator->calculateTotalCogs($orderItems);
        $expectedSalesProfit = $this->calculator->calculateSalesProfit($expectedBilledRevenue, $expectedCogs);
        $expectedTotalCashInflow = $expectedPosCashTendered + $expectedRecoveries;

        // Mathematical Consistency Assertions
        $this->assertEqualsWithDelta(
            $expectedBilledRevenue,
            $expectedPosCashTendered + $expectedPending - (count($orders) > 0 ? 0 : 0),
            0.05,
            "Mathematical Identity Check: Billed Revenue must equal POS Cash Tendered + Pending Receivables on these orders"
        );

        $this->assertEqualsWithDelta(
            $expectedSalesProfit,
            $expectedBilledRevenue - $expectedCogs,
            0.05,
            "Sales Profit must equal Billed Revenue - COGS"
        );

        // 4. Generate the actual Summary PDF via Controller
        $request = new Request([
            'type' => $type,
            'date' => $startStr,
            'startDate' => $startStr,
            'endDate' => $endStr,
            'showHeader' => 'true'
        ]);

        $response = $this->controller->summaryReport($request, $this->em, $this->tenantContext, $this->calculator);

        $this->assertSame(200, $response->getStatusCode(), "PDF report response must be 200 OK");
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'), "Response must be a PDF");
        $content = $response->getContent();
        $this->assertNotEmpty($content, "PDF content must not be empty");
        $this->assertStringStartsWith('%PDF-', $content, "Content must have valid PDF magic header");
    }
}
