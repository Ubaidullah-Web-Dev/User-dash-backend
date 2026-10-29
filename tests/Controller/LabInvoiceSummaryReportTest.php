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
use Symfony\Component\HttpFoundation\Response;

class LabInvoiceSummaryReportTest extends KernelTestCase
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

        // Get company 1 (Unique Healthcare Solutions)
        $company = $this->em->getRepository(Company::class)->find(1);
        $this->assertNotNull($company, 'Company 1 must exist from imported backup');
        $this->company = $company;
        $this->tenantContext->setCurrentCompany($this->company);

        // Instantiate controller and set container
        $this->controller = new LabInvoiceController();
        $this->controller->setContainer($container);
    }

    public function testExactFinancialFiguresForOctober1To15(): void
    {
        $range = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-01', '2026-10-15');
        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        $orderData = $this->em->getRepository(\App\Entity\Order::class)->createQueryBuilder('o')
            ->select('SUM(o.total) as total, COUNT(o.id) as customerCount')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $this->company->getId())
            ->getQuery()
            ->getOneOrNullResult();

        $revenue = round((float) ($orderData['total'] ?? 0));
        $customerCount = (int) ($orderData['customerCount'] ?? 0);

        $totalPending = round((float) $this->em->getRepository(\App\Entity\Order::class)->createQueryBuilder('o')
            ->select('SUM(CASE WHEN o.changeDue < 0 THEN ABS(o.changeDue) ELSE 0 END)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.company = :company')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('company', $this->company->getId())
            ->getQuery()
            ->getSingleScalarResult());

        $receivedPayments = $this->calculator->calculateReceivedPayments($revenue, $totalPending);

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

        $cogs = $this->calculator->calculateTotalCogs($orderItems);
        $salesProfit = $this->calculator->calculateSalesProfit($revenue, $cogs);
        $netIncome = $this->calculator->calculateNetIncome($receivedPayments, 0);

        // Verification of the exact figures for 01 Oct to 15 Oct 2026
        $this->assertSame(24, $customerCount);
        $this->assertEquals(750850.0, $revenue);
        $this->assertEquals(591340.0, $totalPending);
        $this->assertEquals(159510.0, $receivedPayments);
        $this->assertEquals(642537.0, $cogs);
        $this->assertEquals(108313.0, $salesProfit);
        $this->assertGreaterThan(0, $salesProfit, 'Sales profit MUST be positive!');
        $this->assertEquals(159510.0, $netIncome);
    }

    public function testSummaryReportCustomRangeOct1To15GeneratesPdf(): void
    {
        $request = Request::create(
            '/api/admin/labs/invoice/summary?type=custom&startDate=2026-10-01&endDate=2026-10-15&showHeader=true',
            'GET'
        );

        $response = $this->controller->summaryReport(
            $request,
            $this->em,
            $this->tenantContext,
            $this->calculator
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Lab-Summary-Custom', (string) $response->headers->get('Content-Disposition'));
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function testSummaryReportDailyGeneratesPdf(): void
    {
        $request = Request::create(
            '/api/admin/labs/invoice/summary?type=daily&date=2026-10-14&showHeader=true',
            'GET'
        );

        $response = $this->controller->summaryReport(
            $request,
            $this->em,
            $this->tenantContext,
            $this->calculator
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testSummaryReportMonthlyGeneratesPdf(): void
    {
        $request = Request::create(
            '/api/admin/labs/invoice/summary?type=monthly&year=2026&month=10&showHeader=true',
            'GET'
        );

        $response = $this->controller->summaryReport(
            $request,
            $this->em,
            $this->tenantContext,
            $this->calculator
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testSummaryReportYearlyGeneratesPdf(): void
    {
        $request = Request::create(
            '/api/admin/labs/invoice/summary?type=yearly&year=2026&showHeader=true',
            'GET'
        );

        $response = $this->controller->summaryReport(
            $request,
            $this->em,
            $this->tenantContext,
            $this->calculator
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testSummaryReportInvertedDatesHandledGracefully(): void
    {
        $request = Request::create(
            '/api/admin/labs/invoice/summary?type=custom&startDate=2026-10-15&endDate=2026-10-01&showHeader=true',
            'GET'
        );

        $response = $this->controller->summaryReport(
            $request,
            $this->em,
            $this->tenantContext,
            $this->calculator
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
