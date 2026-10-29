<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Service\FinancialCalculatorService;
use PHPUnit\Framework\TestCase;

class FinancialCalculatorTest extends TestCase
{
    private FinancialCalculatorService $calculator;

    protected function setUp(): void
    {
        $this->calculator = new FinancialCalculatorService();
    }

    private function createProduct(
        ?float $price = 100.0,
        ?float $purchasePrice = 70.0,
        ?string $packSize = null,
        string $name = 'Test Product'
    ): Product {
        $p = new Product();
        $p->setName($name);
        $p->setSlug(strtolower(str_replace(' ', '-', $name)) . '-' . uniqid());
        if ($price !== null) {
            $p->setPrice((string) $price);
        }
        if ($purchasePrice !== null) {
            $p->setPurchasePrice((string) $purchasePrice);
        }
        $p->setPackSize($packSize);
        return $p;
    }

    private function createOrderItem(float $price, int|float $quantity, ?Product $product): OrderItem
    {
        $item = new OrderItem();
        $item->setPrice($price);
        $item->setQuantity((int) $quantity);
        if ($product) {
            $item->setProduct($product);
        }
        return $item;
    }

    // =========================================================================
    // SECTION 1: PACK SIZE PARSING (Tests 1 to 45)
    // =========================================================================

    public function testParsePackUnitsWithNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits(null));
    }

    public function testParsePackUnitsWithEmptyString(): void
    {
        $this->assertNull($this->calculator->parsePackUnits(''));
    }

    public function testParsePackUnitsWithWhitespaceOnly(): void
    {
        $this->assertNull($this->calculator->parsePackUnits("   \t\n "));
    }

    public function testParsePackUnitsSingal(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('Singal'));
    }

    public function testParsePackUnitsSingleOne(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('1'));
    }

    public function testParsePackUnitsOneSet(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('1 Set'));
    }

    public function testParsePackUnitsSet(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('SET'));
    }

    public function testParsePackUnits25TestBox(): void
    {
        $this->assertSame(25, $this->calculator->parsePackUnits('25 Test / Box'));
    }

    public function testParsePackUnits30TestBox(): void
    {
        $this->assertSame(30, $this->calculator->parsePackUnits('30 Test / Box'));
    }

    public function testParsePackUnits20TestBox(): void
    {
        $this->assertSame(20, $this->calculator->parsePackUnits('20 Test / Box'));
    }

    public function testParsePackUnits40TestBox(): void
    {
        $this->assertSame(40, $this->calculator->parsePackUnits('40 Test / Box'));
    }

    public function testParsePackUnits100TestBox(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Test / Box'));
    }

    public function testParsePackUnits50TestKit(): void
    {
        $this->assertSame(50, $this->calculator->parsePackUnits('50 Test / kit'));
    }

    public function testParsePackUnits100TestKit(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Test / kit'));
    }

    public function testParsePackUnits96TestKit(): void
    {
        $this->assertSame(96, $this->calculator->parsePackUnits('96 Test / kit'));
    }

    public function testParsePackUnits100TestLowercase(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 test'));
    }

    public function testParsePackUnits25StripVial(): void
    {
        $this->assertSame(25, $this->calculator->parsePackUnits('25 Strip / Vial'));
    }

    public function testParsePackUnits100StripsBox(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Strips / Box'));
    }

    public function testParsePackUnits10StripsVial(): void
    {
        $this->assertSame(10, $this->calculator->parsePackUnits('10 Strips/ vial'));
    }

    public function testParsePackUnits50StripPack(): void
    {
        $this->assertSame(50, $this->calculator->parsePackUnits('50 Strip Pack'));
    }

    public function testParsePackUnits500PcsPackt(): void
    {
        $this->assertSame(500, $this->calculator->parsePackUnits('500 Pcs / Packt'));
    }

    public function testParsePackUnits1000PcsPackt(): void
    {
        $this->assertSame(1000, $this->calculator->parsePackUnits('1000 Pcs / Packt'));
    }

    public function testParsePackUnits100PcsPack(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Pcs / Pack'));
    }

    public function testParsePackUnits200PcsBox(): void
    {
        $this->assertSame(200, $this->calculator->parsePackUnits('200 Pcs / Box'));
    }

    public function testParsePackUnits200PcsPackt(): void
    {
        $this->assertSame(200, $this->calculator->parsePackUnits('200 Pcs / Packt'));
    }

    public function testParsePackUnits72PicePackt(): void
    {
        $this->assertSame(72, $this->calculator->parsePackUnits('72 Pice / Packt'));
    }

    public function testParsePackUnits72PcsPackt(): void
    {
        $this->assertSame(72, $this->calculator->parsePackUnits('72 Pcs / Packt'));
    }

    public function testParsePackUnits100PcsPackt(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Pcs / Packt'));
    }

    public function testParsePackUnits500SheetsPack(): void
    {
        $this->assertSame(500, $this->calculator->parsePackUnits('500 Sheets/Pack'));
    }

    public function testParsePackUnits100TubesVial(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Tubes / Vial'));
    }

    public function testParsePackUnits100TubesBox(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('100 Tubes / Box'));
    }

    public function testParsePackUnits20SlashBox(): void
    {
        $this->assertSame(20, $this->calculator->parsePackUnits('20 / Box'));
    }

    public function testParsePackUnitsBoxOf25(): void
    {
        $this->assertSame(25, $this->calculator->parsePackUnits('Box of 25'));
    }

    public function testParsePackUnitsPackOf100(): void
    {
        $this->assertSame(100, $this->calculator->parsePackUnits('pack of 100'));
    }

    public function testParsePackUnitsKitOf50(): void
    {
        $this->assertSame(50, $this->calculator->parsePackUnits('Kit of 50'));
    }

    public function testParsePackUnitsVolume500mlShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('500mL'));
    }

    public function testParsePackUnitsVolume5LShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('5L'));
    }

    public function testParsePackUnitsVolume20LShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('20 L'));
    }

    public function testParsePackUnitsVolume350mlShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('350mL'));
    }

    public function testParsePackUnitsVolume100mlShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('100 ml'));
    }

    public function testParsePackUnitsVolumeComposite3x10mlShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('3 x 10ml'));
    }

    public function testParsePackUnitsVolumeComposite4x250mlShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('4 x 250 ml'));
    }

    public function testParsePackUnitsPacktShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('Packt'));
    }

    public function testParsePackUnitsZeroShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('0 Tests'));
    }

    public function testParsePackUnitsNegativeShouldBeNull(): void
    {
        $this->assertNull($this->calculator->parsePackUnits('-10 Tests'));
    }

    // =========================================================================
    // SECTION 2: ORDER ITEM UNIT COST CALCULATION (Tests 46 to 95)
    // =========================================================================

    public function testUnitCostNullProduct(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(500.0, null));
    }

    public function testUnitCostProductWithNullPurchasePrice(): void
    {
        $p = $this->createProduct(100.0, null);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostProductWithZeroPurchasePrice(): void
    {
        $p = $this->createProduct(100.0, 0.0);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostProductWithNegativePurchasePrice(): void
    {
        $p = $this->createProduct(100.0, -50.0);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostStandardSaleAtCatalogPrice(): void
    {
        $p = $this->createProduct(1000.0, 600.0);
        $this->assertSame(600.0, $this->calculator->calculateOrderItemUnitCost(1000.0, $p));
    }

    public function testUnitCostStandardSaleWithNormalDiscount(): void
    {
        $p = $this->createProduct(1000.0, 600.0);
        // Sold for 850 (discounted, but >= purchase price)
        $this->assertSame(600.0, $this->calculator->calculateOrderItemUnitCost(850.0, $p));
    }

    public function testUnitCostStandardSaleAtPurchasePriceBreakeven(): void
    {
        $p = $this->createProduct(1000.0, 600.0);
        $this->assertSame(600.0, $this->calculator->calculateOrderItemUnitCost(600.0, $p));
    }

    public function testUnitCostClearanceSaleSlightlyBelowCost(): void
    {
        // A4 Paper: catalog price 1200, purchase price 1000, sold for 900
        $p = $this->createProduct(1200.0, 1000.0, '500 Sheets/Pack');
        $this->assertSame(1000.0, $this->calculator->calculateOrderItemUnitCost(900.0, $p));
    }

    public function testUnitCostClearanceSaleWithoutPackSize(): void
    {
        $p = $this->createProduct(1200.0, 1000.0);
        $this->assertSame(1000.0, $this->calculator->calculateOrderItemUnitCost(800.0, $p));
    }

    public function testUnitCostFreeSample(): void
    {
        $p = $this->createProduct(500.0, 250.0);
        $this->assertSame(250.0, $this->calculator->calculateOrderItemUnitCost(0.0, $p));
    }

    public function testUnitCostNegativeItemPrice(): void
    {
        $p = $this->createProduct(500.0, 250.0);
        $this->assertSame(250.0, $this->calculator->calculateOrderItemUnitCost(-10.0, $p));
    }

    public function testUnitCostTSHGPPackToUnitSale(): void
    {
        // TSH GP: 25 Test / Box, catalog price 12000, catalog purchase price 10500
        // Sold in units at 480 each
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $this->assertSame(420.0, $this->calculator->calculateOrderItemUnitCost(480.0, $p));
    }

    public function testUnitCostTSHGPPackToUnitSaleWithDiscount(): void
    {
        // Sold for 450 each (discounted unit price)
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $this->assertSame(420.0, $this->calculator->calculateOrderItemUnitCost(450.0, $p));
    }

    public function testUnitCostTSHGPPackToUnitSaleWithMarkup(): void
    {
        // Sold for 500 each
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $this->assertSame(420.0, $this->calculator->calculateOrderItemUnitCost(500.0, $p));
    }

    public function testUnitCostTSHGPSoldAsFullBox(): void
    {
        // If someone sells 1 full box at 12000
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $this->assertSame(10500.0, $this->calculator->calculateOrderItemUnitCost(12000.0, $p));
    }

    public function testUnitCostTSHGPSoldAsFullBoxDiscounted(): void
    {
        // If someone sells 1 full box discounted to 11000
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $this->assertSame(10500.0, $this->calculator->calculateOrderItemUnitCost(11000.0, $p));
    }

    public function testUnitCostHBA1CGPUnitToPackSale(): void
    {
        // HBA1C GP: 25 Test / Box, catalog price 420 (per test), cost 350 (per test)
        // In order 24, sold 2 boxes at 10500 each
        $p = $this->createProduct(420.0, 350.0, '25 Test / Box', 'HBA1C GP');
        $this->assertSame(8750.0, $this->calculator->calculateOrderItemUnitCost(10500.0, $p));
    }

    public function testUnitCostHBA1CGPSoldPerTest(): void
    {
        // If someone sells 1 test at 420
        $p = $this->createProduct(420.0, 350.0, '25 Test / Box', 'HBA1C GP');
        $this->assertSame(350.0, $this->calculator->calculateOrderItemUnitCost(420.0, $p));
    }

    public function testUnitCost100StripsBoxSoldPerStrip(): void
    {
        // COMBI 10: 100 Strips / Box, price 6000, cost 3650
        // If sold per strip at 60 each:
        $p = $this->createProduct(6000.0, 3650.0, '100 Strips / Box', 'COMBI 10');
        $this->assertSame(36.5, $this->calculator->calculateOrderItemUnitCost(60.0, $p));
    }

    public function testUnitCost100StripsBoxSoldFullBox(): void
    {
        $p = $this->createProduct(6000.0, 3650.0, '100 Strips / Box', 'COMBI 10');
        $this->assertSame(3650.0, $this->calculator->calculateOrderItemUnitCost(6000.0, $p));
    }

    public function testUnitCost500PcsPackSoldPerPiece(): void
    {
        $p = $this->createProduct(2500.0, 1500.0, '500 Pcs / Packt', 'Tips');
        // Expected unit price is 2500 / 500 = 5.0, cost is 1500 / 500 = 3.0
        $this->assertSame(3.0, $this->calculator->calculateOrderItemUnitCost(5.0, $p));
    }

    public function testUnitCost40TestBoxSoldPerTest(): void
    {
        $p = $this->createProduct(4000.0, 3200.0, '40 Test / Box');
        // Unit expected price: 100, cost: 80
        $this->assertSame(80.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCost50TestKitSoldPerTest(): void
    {
        $p = $this->createProduct(10000.0, 7500.0, '50 Test / kit');
        // Unit expected price: 200, cost: 150
        $this->assertSame(150.0, $this->calculator->calculateOrderItemUnitCost(200.0, $p));
    }

    public function testUnitCostHeuristicDeepDiscountNoPackSize(): void
    {
        // Price 10000, cost 8000 (80% ratio). Sold at 400 (< 50% of cost)
        $p = $this->createProduct(10000.0, 8000.0, null);
        // Fallback ratio: 400 * 0.8 = 320.0
        $this->assertSame(320.0, $this->calculator->calculateOrderItemUnitCost(400.0, $p));
    }

    public function testUnitCostHeuristicBulkSaleNoPackSize(): void
    {
        // Price 100, cost 70 (70% ratio). Sold at 1000 (> 2x catalog price)
        $p = $this->createProduct(100.0, 70.0, null);
        // Fallback ratio: 1000 * 0.7 = 700.0
        $this->assertSame(700.0, $this->calculator->calculateOrderItemUnitCost(1000.0, $p));
    }

    public function testUnitCostAPTTRegentCase(): void
    {
        // Product 158: APTT Regent, catalog price 3325, cost 1830
        // Sold for 13300 (4 vials)
        $p = $this->createProduct(3325.0, 1830.0, '4ml', 'APTT Regent');
        // 13300 * (1830 / 3325) = 7320.0
        $this->assertSame(7320.0, $this->calculator->calculateOrderItemUnitCost(13300.0, $p));
    }

    public function testUnitCostZeroCatalogPriceWithCost(): void
    {
        $p = $this->createProduct(0.0, 500.0);
        $this->assertSame(500.0, $this->calculator->calculateOrderItemUnitCost(600.0, $p));
    }

    public function testUnitCostNegativeCatalogPrice(): void
    {
        $p = $this->createProduct(-100.0, 500.0);
        $this->assertSame(500.0, $this->calculator->calculateOrderItemUnitCost(600.0, $p));
    }

    public function testUnitCostHighPrecisionRounding(): void
    {
        $p = $this->createProduct(100.0, 33.3333);
        $this->assertSame(33.3333, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostSinglePackSizeWithPackUnitSale(): void
    {
        // Pack size is "Singal", but sold for 480 when cost is 10500 and price is 12000
        $p = $this->createProduct(12000.0, 10500.0, 'Singal');
        // Heuristic fallback kicks in because 480 < 5250 (half cost): 480 * (10500/12000) = 420
        $this->assertSame(420.0, $this->calculator->calculateOrderItemUnitCost(480.0, $p));
    }

    public function testUnitCostProductWithCostGreaterThanPriceInCatalog(): void
    {
        // A product entered with mistake cost 1200, price 1000
        $p = $this->createProduct(1000.0, 1200.0);
        $this->assertSame(1200.0, $this->calculator->calculateOrderItemUnitCost(1000.0, $p));
    }

    public function testUnitCostLargePriceAndCost(): void
    {
        $p = $this->createProduct(1000000.0, 800000.0);
        $this->assertSame(800000.0, $this->calculator->calculateOrderItemUnitCost(1000000.0, $p));
    }

    public function testUnitCostTinyFractionalPriceAndCost(): void
    {
        $p = $this->createProduct(0.50, 0.35);
        $this->assertSame(0.35, $this->calculator->calculateOrderItemUnitCost(0.50, $p));
    }

    public function testUnitCost100TubesVialSoldPerTube(): void
    {
        $p = $this->createProduct(1000.0, 500.0, '100 Tubes / Vial');
        $this->assertSame(5.0, $this->calculator->calculateOrderItemUnitCost(10.0, $p));
    }

    public function testUnitCost20BoxSoldPerItem(): void
    {
        $p = $this->createProduct(2000.0, 1400.0, '20 / Box');
        $this->assertSame(70.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCost50StripPackSoldPerStrip(): void
    {
        $p = $this->createProduct(2500.0, 1500.0, '50 Strip Pack');
        $this->assertSame(30.0, $this->calculator->calculateOrderItemUnitCost(50.0, $p));
    }

    public function testUnitCostBoxOf25SoldPerItem(): void
    {
        $p = $this->createProduct(5000.0, 3750.0, 'Box of 25');
        $this->assertSame(150.0, $this->calculator->calculateOrderItemUnitCost(200.0, $p));
    }

    public function testUnitCostPackOf100SoldPerItem(): void
    {
        $p = $this->createProduct(8000.0, 5000.0, 'Pack of 100');
        $this->assertSame(50.0, $this->calculator->calculateOrderItemUnitCost(80.0, $p));
    }

    public function testUnitCostKitOf50SoldPerItem(): void
    {
        $p = $this->createProduct(15000.0, 10000.0, 'Kit of 50');
        $this->assertSame(200.0, $this->calculator->calculateOrderItemUnitCost(300.0, $p));
    }

    public function testUnitCost96TestKitSoldPerTest(): void
    {
        $p = $this->createProduct(9600.0, 4800.0, '96 Test / kit');
        $this->assertSame(50.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCost72PcsPacktSoldPerPiece(): void
    {
        $p = $this->createProduct(720.0, 360.0, '72 Pcs / Packt');
        $this->assertSame(5.0, $this->calculator->calculateOrderItemUnitCost(10.0, $p));
    }

    public function testUnitCost10StripsVialSoldPerStrip(): void
    {
        $p = $this->createProduct(500.0, 350.0, '10 Strips/ vial');
        $this->assertSame(35.0, $this->calculator->calculateOrderItemUnitCost(50.0, $p));
    }

    public function testUnitCostStandardItemSmallDiscountKeepsExactCost(): void
    {
        $p = $this->createProduct(500.0, 350.0, null);
        // Sold for 480 (discount of 20, well above cost 350)
        $this->assertSame(350.0, $this->calculator->calculateOrderItemUnitCost(480.0, $p));
    }

    public function testUnitCostStandardItemSoldAtPremiumKeepsExactCost(): void
    {
        $p = $this->createProduct(500.0, 350.0, null);
        // Sold for 550 (slight markup, but < 2x catalog price)
        $this->assertSame(350.0, $this->calculator->calculateOrderItemUnitCost(550.0, $p));
    }

    public function testUnitCostWhenItemPriceEqualsPurchasePrice(): void
    {
        $p = $this->createProduct(1000.0, 750.0, null);
        $this->assertSame(750.0, $this->calculator->calculateOrderItemUnitCost(750.0, $p));
    }

    public function testUnitCostCatalogPriceZeroCostZero(): void
    {
        $p = $this->createProduct(0.0, 0.0, null);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostCatalogNullBoth(): void
    {
        $p = $this->createProduct(null, null, null);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemUnitCost(100.0, $p));
    }

    public function testUnitCostBothCostAndPriceIdentical(): void
    {
        $p = $this->createProduct(500.0, 500.0, null);
        $this->assertSame(500.0, $this->calculator->calculateOrderItemUnitCost(500.0, $p));
    }

    public function testUnitCostBothCostAndPriceIdenticalWithPackSize(): void
    {
        $p = $this->createProduct(1000.0, 1000.0, '100 Strips / Box');
        // Sold at 10 per strip
        $this->assertSame(10.0, $this->calculator->calculateOrderItemUnitCost(10.0, $p));
    }

    public function testUnitCostItemPriceExactHalfOfPurchasePrice(): void
    {
        $p = $this->createProduct(1000.0, 800.0, null);
        // Item price exactly 400 (half of 800)
        $this->assertSame(800.0, $this->calculator->calculateOrderItemUnitCost(400.0, $p));
    }

    // =========================================================================
    // SECTION 3: ITEM COGS, TOTAL COGS, SALES PROFIT, AND NET INCOME (Tests 96 to 135)
    // =========================================================================

    public function testItemCogsNormalItem(): void
    {
        $p = $this->createProduct(100.0, 60.0);
        $this->assertSame(300.0, $this->calculator->calculateOrderItemCogs(100.0, 5, $p));
    }

    public function testItemCogsZeroQuantity(): void
    {
        $p = $this->createProduct(100.0, 60.0);
        $this->assertSame(0.0, $this->calculator->calculateOrderItemCogs(100.0, 0, $p));
    }

    public function testItemCogsNegativeQuantityRefund(): void
    {
        $p = $this->createProduct(100.0, 60.0);
        $this->assertSame(-120.0, $this->calculator->calculateOrderItemCogs(100.0, -2, $p));
    }

    public function testItemCogsTSHGPActualCase(): void
    {
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        // Sold 25 tests at 480 each
        $cogs = $this->calculator->calculateOrderItemCogs(480.0, 25, $p);
        $this->assertSame(10500.0, $cogs);
    }

    public function testItemCogsSingleTestFromTSHGP(): void
    {
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        // Sold 1 test at 480
        $cogs = $this->calculator->calculateOrderItemCogs(480.0, 1, $p);
        $this->assertSame(420.0, $cogs);
    }

    public function testItemCogsFiveTestsFromTSHGP(): void
    {
        $p = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        // Sold 5 tests at 480
        $cogs = $this->calculator->calculateOrderItemCogs(480.0, 5, $p);
        $this->assertSame(2100.0, $cogs);
    }

    public function testItemCogsHBA1CGPTwoPacks(): void
    {
        $p = $this->createProduct(420.0, 350.0, '25 Test / Box', 'HBA1C GP');
        // Sold 2 packs at 10500 each
        $cogs = $this->calculator->calculateOrderItemCogs(10500.0, 2, $p);
        $this->assertSame(17500.0, $cogs);
    }

    public function testItemCogsNullProduct(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateOrderItemCogs(100.0, 5, null));
    }

    public function testTotalCogsEmptyCollection(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateTotalCogs([]));
    }

    public function testTotalCogsMultipleItems(): void
    {
        $p1 = $this->createProduct(100.0, 60.0);
        $p2 = $this->createProduct(200.0, 120.0);

        $items = [
            $this->createOrderItem(100.0, 2, $p1), // 2 * 60 = 120
            $this->createOrderItem(200.0, 3, $p2), // 3 * 120 = 360
        ];

        $this->assertSame(480.0, $this->calculator->calculateTotalCogs($items));
    }

    public function testTotalCogsIncludesPackAndStandardItems(): void
    {
        $pTSH = $this->createProduct(12000.0, 10500.0, '25 Test / Box', 'TSH GP');
        $pHCV = $this->createProduct(70.0, 63.0, '25 Test / Box', 'HCV Device');

        $items = [
            $this->createOrderItem(480.0, 25, $pTSH), // 25 * 420 = 10500
            $this->createOrderItem(70.0, 25, $pHCV),  // 25 * 63 = 1575
        ];

        $this->assertSame(12075.0, $this->calculator->calculateTotalCogs($items));
    }

    public function testTotalCogsIgnoresInvalidEntries(): void
    {
        $p = $this->createProduct(100.0, 50.0);
        $items = [
            $this->createOrderItem(100.0, 2, $p),
            'invalid_entry',
            null,
            $this->createOrderItem(100.0, 0, $p),
        ];

        $this->assertSame(100.0, $this->calculator->calculateTotalCogs($items));
    }

    public function testSalesProfitPositive(): void
    {
        $this->assertSame(108313.0, $this->calculator->calculateSalesProfit(750850.0, 642537.0));
    }

    public function testSalesProfitZero(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateSalesProfit(50000.0, 50000.0));
    }

    public function testSalesProfitNegative(): void
    {
        $this->assertSame(-5000.0, $this->calculator->calculateSalesProfit(10000.0, 15000.0));
    }

    public function testSalesProfitZeroRevenue(): void
    {
        $this->assertSame(-12000.0, $this->calculator->calculateSalesProfit(0.0, 12000.0));
    }

    public function testSalesProfitZeroCogs(): void
    {
        $this->assertSame(50000.0, $this->calculator->calculateSalesProfit(50000.0, 0.0));
    }

    public function testSalesProfitBothZero(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateSalesProfit(0.0, 0.0));
    }

    public function testTotalPendingCalculation(): void
    {
        $o1 = new Order();
        $o1->setChangeDue(-500.0);

        $o2 = new Order();
        $o2->setChangeDue(0.0);

        $o3 = new Order();
        $o3->setChangeDue(-1250.0);

        $o4 = new Order();
        $o4->setChangeDue(200.0); // change given back to customer, not pending

        $this->assertSame(1750.0, $this->calculator->calculateTotalPending([$o1, $o2, $o3, $o4]));
    }

    public function testTotalPendingEmptyOrders(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateTotalPending([]));
    }

    public function testTotalPendingNullChangeDue(): void
    {
        $o = new Order();
        $o->setChangeDue(null);
        $this->assertSame(0.0, $this->calculator->calculateTotalPending([$o]));
    }

    public function testReceivedPaymentsFullPayment(): void
    {
        $this->assertSame(1000.0, $this->calculator->calculateReceivedPayments(1000.0, 0.0));
    }

    public function testReceivedPaymentsPartialPending(): void
    {
        $this->assertSame(159510.0, $this->calculator->calculateReceivedPayments(750850.0, 591340.0));
    }

    public function testReceivedPaymentsPendingExceedsRevenueReturnsZero(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateReceivedPayments(500.0, 700.0));
    }

    public function testReceivedPaymentsZeroBoth(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateReceivedPayments(0.0, 0.0));
    }

    public function testNetIncomePositive(): void
    {
        $this->assertSame(159510.0, $this->calculator->calculateNetIncome(159510.0, 0.0));
    }

    public function testNetIncomeWithExpenses(): void
    {
        $this->assertSame(109510.0, $this->calculator->calculateNetIncome(159510.0, 50000.0));
    }

    public function testNetIncomeNegative(): void
    {
        $this->assertSame(-25000.0, $this->calculator->calculateNetIncome(25000.0, 50000.0));
    }

    public function testNetIncomeZeroExpenses(): void
    {
        $this->assertSame(75000.0, $this->calculator->calculateNetIncome(75000.0, 0.0));
    }

    public function testNetIncomeBothZero(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateNetIncome(0.0, 0.0));
    }

    public function testSalesProfitWithDecimals(): void
    {
        $this->assertSame(100.45, $this->calculator->calculateSalesProfit(500.70, 400.25));
    }

    public function testNetIncomeWithDecimals(): void
    {
        $this->assertSame(349.55, $this->calculator->calculateNetIncome(500.75, 151.20));
    }

    public function testReceivedPaymentsWithDecimals(): void
    {
        $this->assertSame(250.33, $this->calculator->calculateReceivedPayments(500.83, 250.50));
    }

    public function testLargeVolumeCogsCalculation(): void
    {
        $p = $this->createProduct(20.0, 15.0);
        $this->assertSame(150000.0, $this->calculator->calculateOrderItemCogs(20.0, 10000, $p));
    }

    public function testVerySmallFractionalCogsCalculation(): void
    {
        $p = $this->createProduct(1.0, 0.60);
        $this->assertSame(0.60, $this->calculator->calculateOrderItemCogs(1.0, 1, $p));
    }

    // =========================================================================
    // SECTION 4: DATE RANGE RESOLUTION (Tests 136 to 170)
    // =========================================================================

    public function testResolveDateRangeDailyExplicit(): void
    {
        $res = $this->calculator->resolveDateRange('daily', '2026-10-10');
        $this->assertSame('2026-10-10 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('10 Oct 2026', $res['periodLabel']);
        $this->assertSame('daily', $res['type']);
    }

    public function testResolveDateRangeDailyNullDateStr(): void
    {
        $res = $this->calculator->resolveDateRange('daily', null);
        $today = date('Y-m-d');
        $this->assertSame($today . ' 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame($today . ' 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeDailyInvalidString(): void
    {
        $res = $this->calculator->resolveDateRange('daily', 'not-a-date');
        $today = date('Y-m-d');
        $this->assertSame($today . ' 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeMonthlyStandard(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 10);
        $this->assertSame('2026-10-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-31 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('October 2026', $res['periodLabel']);
        $this->assertSame('monthly', $res['type']);
    }

    public function testResolveDateRangeMonthlyLeapYearFeb(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2024, 2);
        $this->assertSame('2024-02-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2024-02-29 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('February 2024', $res['periodLabel']);
    }

    public function testResolveDateRangeMonthlyNonLeapYearFeb(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 2);
        $this->assertSame('2026-02-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('February 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeMonthly30DayMonth(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 4);
        $this->assertSame('2026-04-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('April 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeMonthlyInvalidMonthAbove12(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 15);
        $this->assertSame(2026, (int) $res['startDate']->format('Y'));
        // Month defaults to current month
        $this->assertSame((int) date('n'), (int) $res['startDate']->format('n'));
    }

    public function testResolveDateRangeMonthlyInvalidMonthZero(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 0);
        $this->assertSame((int) date('n'), (int) $res['startDate']->format('n'));
    }

    public function testResolveDateRangeMonthlyInvalidYearNegative(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, -5, 5);
        $this->assertSame((int) date('Y'), (int) $res['startDate']->format('Y'));
    }

    public function testResolveDateRangeMonthlyInvalidYearFuture(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 3500, 5);
        $this->assertSame((int) date('Y'), (int) $res['startDate']->format('Y'));
    }

    public function testResolveDateRangeMonthlyNullParams(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, null, null);
        $this->assertSame((int) date('Y'), (int) $res['startDate']->format('Y'));
        $this->assertSame((int) date('n'), (int) $res['startDate']->format('n'));
    }

    public function testResolveDateRangeYearly(): void
    {
        $res = $this->calculator->resolveDateRange('yearly', null, 2026);
        $this->assertSame('2026-01-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026', $res['periodLabel']);
        $this->assertSame('yearly', $res['type']);
    }

    public function testResolveDateRangeYearlyNullYear(): void
    {
        $res = $this->calculator->resolveDateRange('yearly', null, null);
        $this->assertSame(date('Y'), $res['periodLabel']);
    }

    public function testResolveDateRangeYearlyInvalidYear(): void
    {
        $res = $this->calculator->resolveDateRange('yearly', null, 9999);
        $this->assertSame(date('Y'), $res['periodLabel']);
    }

    public function testResolveDateRangeCustomOct1To15(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-01', '2026-10-15');
        $this->assertSame('2026-10-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('01 Oct 2026 to 15 Oct 2026', $res['periodLabel']);
        $this->assertSame('custom', $res['type']);
    }

    public function testResolveDateRangeCustomSwapsInvertedDates(): void
    {
        // User mistakenly passes end date before start date
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-15', '2026-10-01');
        $this->assertSame('2026-10-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('01 Oct 2026 to 15 Oct 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeCustomSameDay(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-05', '2026-10-05');
        $this->assertSame('2026-10-05 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('05 Oct 2026 to 05 Oct 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeCustomNullStart(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, null, '2026-10-15');
        $this->assertInstanceOf(\DateTime::class, $res['startDate']);
        $this->assertInstanceOf(\DateTime::class, $res['endDate']);
    }

    public function testResolveDateRangeCustomNullEnd(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-01', null);
        $this->assertInstanceOf(\DateTime::class, $res['startDate']);
        $this->assertInstanceOf(\DateTime::class, $res['endDate']);
    }

    public function testResolveDateRangeCustomInvalidFormatStrings(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, 'invalid-date', 'gibberish');
        $today = date('Y-m-d');
        $this->assertSame($today . ' 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame($today . ' 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeUnknownTypeDefaultsToCustom(): void
    {
        $res = $this->calculator->resolveDateRange('quarterly', null, null, null, '2026-01-01', '2026-03-31');
        $this->assertSame('custom', $res['type']);
        $this->assertSame('2026-01-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeCaseInsensitivity(): void
    {
        $res = $this->calculator->resolveDateRange('DAILY', '2026-10-12');
        $this->assertSame('daily', $res['type']);
        $this->assertSame('2026-10-12 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeMonthlyCaseInsensitive(): void
    {
        $res = $this->calculator->resolveDateRange('MONTHLY', null, 2026, 6);
        $this->assertSame('monthly', $res['type']);
        $this->assertSame('June 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeYearlyCaseInsensitive(): void
    {
        $res = $this->calculator->resolveDateRange('YEARLY', null, 2026);
        $this->assertSame('yearly', $res['type']);
        $this->assertSame('2026', $res['periodLabel']);
    }

    public function testResolveDateRangeCustomWhitespaceInDates(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '  2026-10-01  ', '  2026-10-15  ');
        $this->assertSame('2026-10-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeYearBoundaryCrossing(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2025-12-01', '2026-01-31');
        $this->assertSame('2025-12-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
        $this->assertSame('01 Dec 2025 to 31 Jan 2026', $res['periodLabel']);
    }

    public function testResolveDateRangeMonth31stLastDay(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 12);
        $this->assertSame('2026-12-31 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeMonth30thLastDay(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2026, 9);
        $this->assertSame('2026-09-30 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeNullTypeDefaultsToDaily(): void
    {
        $res = $this->calculator->resolveDateRange(null, '2026-10-10');
        $this->assertSame('daily', $res['type']);
        $this->assertSame('2026-10-10 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeWhitespaceTypeDefaultsToDaily(): void
    {
        $res = $this->calculator->resolveDateRange('   ', '2026-10-10');
        $this->assertSame('daily', $res['type']);
    }

    public function testResolveDateRangeCustomBothEmpty(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '', '');
        $today = date('Y-m-d');
        $this->assertSame($today . ' 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeCustomOneValidOneInvalid(): void
    {
        $res = $this->calculator->resolveDateRange('custom', null, null, null, '2026-10-01', 'bad-date');
        $this->assertSame('2026-10-01 00:00:00', $res['startDate']->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTime::class, $res['endDate']);
    }

    public function testResolveDateRangeYearBoundaryFebruary29OnLeapYear(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2028, 2);
        $this->assertSame('2028-02-29 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }

    public function testResolveDateRangeYear2000CenturyLeapYear(): void
    {
        $res = $this->calculator->resolveDateRange('monthly', null, 2000, 2);
        $this->assertSame('2000-02-29 23:59:59', $res['endDate']->format('Y-m-d H:i:s'));
    }
}
