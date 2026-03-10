<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\RegisteredCustomer;
use App\Entity\User;
use App\Entity\Vendor;
use App\Entity\VendorOrder;
use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:seed-pharmacy',
    description: 'Seeds the marketplace with pharmacy products, customers, vendors, and orders.',
)]
class SeedPharmacyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);

        if (!$user) {
            $io->error('No users found. Please register at least one user first.');
            return Command::FAILURE;
        }

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $io->info('Clearing existing data (except users)...');

        $tablesToTruncate = [
            'cart_item',
            'vendor_order',
            'vendor',
            'product_image',
            'order_item',
            '`order`',
            'registered_customer',
            'product',
            'category'
        ];

        // Disable foreign key checks to allow truncating tables with relations
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tablesToTruncate as $table) {
            try {
                $sql = 'TRUNCATE TABLE ' . $table;
                $connection->executeStatement($sql);
            } catch (\Exception $e) {
                $io->warning("Could not truncate $table: " . $e->getMessage());
            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $io->success('Existing data cleared.');

        // Seed Categories
        $io->info('Seeding Categories...');
        $categoriesData = [
            'Pain Relief' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae',
            'Cold & Flu' => 'https://images.unsplash.com/photo-1550572017-edb301b1fc64',
            'Vitamins & Supplements' => 'https://images.unsplash.com/photo-1577401239170-897942555fb3',
            'First Aid' => 'https://images.unsplash.com/photo-1603398938378-e54eab446dde',
            'Skin Care' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571',
            'Digestive Health' => 'https://images.unsplash.com/photo-1628771065518-0d82f1938462',
        ];

        $categories = [];
        $categoriesList = [];
        foreach ($categoriesData as $name => $img) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug(strtolower($this->slugger->slug($name)));
            $category->setImage($img);
            $this->entityManager->persist($category);
            $categories[$name] = $category;
            $categoriesList[] = $category;
        }

        // Seed Vendors
        $io->info('Seeding 20 Vendors...');
        $vendorsList = [];
        for ($i = 1; $i <= 20; $i++) {
            $vendor = new Vendor();
            $vendor->setName('Vendor Supplier ' . $i);
            $vendor->setEmail('vendor' . $i . '@example.com');
            $vendor->setPhone('+18005550' . str_pad((string)$i, 3, '0', STR_PAD_LEFT));
            $vendor->setCompanyName('Pharma Distrib ' . $i . ' LLC');
            $vendor->setAddress(rand(10, 999) . ' Supply Chain Rd, Suite ' . $i);
            $vendor->setStatus(rand(1, 10) > 2 ? 'active' : 'inactive');
            
            // Assign a random category
            $randomCategory = $categoriesList[array_rand($categoriesList)];
            $vendor->setCategory($randomCategory);
            
            $this->entityManager->persist($vendor);
            $vendorsList[] = $vendor;
        }

        // Seed Products
        $io->info('Seeding 100 Products...');
        $baseProducts = [
            'Pain Relief' => [
                ['name' => 'Paracetamol 500mg', 'unit' => 'tablets', 'pack' => '20s', 'company' => 'PharmaCorp', 'price' => 5.50],
                ['name' => 'Ibuprofen 400mg', 'unit' => 'tablets', 'pack' => '10s', 'company' => 'HealthMakers', 'price' => 8.00],
                ['name' => 'Aspirin 300mg', 'unit' => 'tablets', 'pack' => '30s', 'company' => 'Global Med', 'price' => 6.25],
            ],
            'Cold & Flu' => [
                ['name' => 'Cough Syrup', 'unit' => 'ml', 'pack' => '100ml', 'company' => 'Relief Labs', 'price' => 12.00],
                ['name' => 'Nasal Spray', 'unit' => 'ml', 'pack' => '15ml', 'company' => 'ClearBreathe', 'price' => 15.50],
                ['name' => 'Throat Lozenges', 'unit' => 'lozenges', 'pack' => '24s', 'company' => 'Soothies', 'price' => 7.50],
            ],
            'Vitamins & Supplements' => [
                ['name' => 'Vitamin C 1000mg', 'unit' => 'tablets', 'pack' => '60s', 'company' => 'VitaBoost', 'price' => 25.00],
                ['name' => 'Multivitamin Once Daily', 'unit' => 'capsules', 'pack' => '30s', 'company' => 'HealthEssentials', 'price' => 30.00],
                ['name' => 'Omega-3 Fish Oil', 'unit' => 'capsules', 'pack' => '90s', 'company' => 'OceanHealth', 'price' => 45.00],
                ['name' => 'Vitamin D3 2000 IU', 'unit' => 'capsules', 'pack' => '60s', 'company' => 'SunShine Med', 'price' => 20.00],
            ],
            'First Aid' => [
                ['name' => 'Adhesive Bandages', 'unit' => 'strips', 'pack' => '50s', 'company' => 'CureAll', 'price' => 4.00],
                ['name' => 'Antiseptic Cream', 'unit' => 'g', 'pack' => '30g', 'company' => 'HealFast', 'price' => 9.50],
                ['name' => 'Cotton Wool', 'unit' => 'g', 'pack' => '100g', 'company' => 'SoftCare', 'price' => 3.00],
            ],
            'Skin Care' => [
                ['name' => 'Moisturizing Lotion', 'unit' => 'ml', 'pack' => '250ml', 'company' => 'DermaCare', 'price' => 22.00],
                ['name' => 'Sunscreen SPF 50', 'unit' => 'ml', 'pack' => '150ml', 'company' => 'SunProtect', 'price' => 35.00],
                ['name' => 'Acne Treatment Gel', 'unit' => 'g', 'pack' => '20g', 'company' => 'ClearSkin', 'price' => 18.50],
            ],
            'Digestive Health' => [
                ['name' => 'Antacid Tablets', 'unit' => 'tablets', 'pack' => '24s', 'company' => 'StomachEase', 'price' => 6.00],
                ['name' => 'Probiotic Capsules', 'unit' => 'capsules', 'pack' => '30s', 'company' => 'GutHealth', 'price' => 40.00],
            ],
        ];

        // Ensure exactly 100 products
        $totalCount = 0;
        $catKeys = array_keys($categoriesData);
        $productsList = [];
        
        while ($totalCount < 100) {
            foreach ($catKeys as $catName) {
                if ($totalCount >= 100) break;

                $category = $categories[$catName];
                $baseList = $baseProducts[$catName];
                $baseItem = $baseList[$totalCount % count($baseList)];

                $product = new Product();
                // Make name unique
                $uniqueName = $baseItem['name'] . ' Variant ' . ($totalCount + 1);
                $product->setName($uniqueName);
                $product->setSlug(strtolower($this->slugger->slug($uniqueName)) . '-' . uniqid());
                
                $price = $baseItem['price'] + rand(-2, 10);
                $product->setPrice((string) $price);
                $product->setPurchasePrice((string) ($price * 0.7)); // 30% margin
                
                $product->setDescription('High quality ' . $baseItem['name'] . ' for your health and wellness needs. Approved and tested. Unit number ' . ($totalCount + 1));
                $product->setStock(rand(50, 500));
                $product->setMinimumStock(rand(10, 20));
                $product->setIsRecommended(rand(1, 10) > 8);
                $product->setUnit($baseItem['unit']);
                $product->setCompanyName($baseItem['company']);
                $product->setPackSize($baseItem['pack']);
                $product->setBatchNumber('BATCH-' . strtoupper(uniqid()));
                
                $expiryDate = new \DateTimeImmutable('+' . rand(6, 36) . ' months');
                $product->setExpiryDate($expiryDate);

                $product->setCategory($category);
                $product->setUser($user);

                // Add 1 image to each product for speed
                $img = new ProductImage();
                $img->setUrl($categoriesData[$catName] . '?q=80&w=400&auto=format&fit=crop');
                $product->addImage($img);

                $this->entityManager->persist($product);
                $productsList[] = $product;
                $totalCount++;
            }
        }

        // Seed 50 Customers
        $io->info('Seeding 50 Customers...');
        $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
        $customersList = [];
        
        for ($i = 1; $i <= 50; $i++) {
            $customer = new RegisteredCustomer();
            $customer->setName('Customer ' . $i);
            
            // Generate unique phone
            $phone = '+1' . str_pad((string) rand(100000000, 999999999), 10, '0', STR_PAD_LEFT) . $i;
            $customer->setPhone($phone);
            
            $customer->setCity($cities[array_rand($cities)]);
            $customer->setAddress('123 Health Ave, Suite ' . rand(100, 500));
            $customer->setLabName(rand(1, 10) > 7 ? 'Clinic ' . $i : null);

            // Customers don't necessarily have totalSpent initially, or we assign 0
            $customer->setTotalSpent(0.0);
            
            $this->entityManager->persist($customer);
            $customersList[] = $customer;
        }

        // Seed Vendor Orders
        $io->info('Seeding Vendor Orders...');
        $statuses = ['pending', 'approved', 'received', 'cancelled'];
        for ($i = 0; $i < 80; $i++) {
            $vendorOrder = new VendorOrder();
            $vendorOrder->setVendor($vendorsList[array_rand($vendorsList)]);
            $vendorOrder->setProduct($productsList[array_rand($productsList)]);
            $vendorOrder->setQuantity(rand(50, 500));
            
            $status = $statuses[array_rand($statuses)];
            $vendorOrder->setStatus($status);
            if ($status === 'received') {
                $vendorOrder->setReceivedAt(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
            }
            $vendorOrder->setComment('Restock order #' . ($i + 1000));
            
            $this->entityManager->persist($vendorOrder);
        }

        // Seed Customer Orders
        $io->info('Seeding Customer Orders...');
        for ($i = 0; $i < 120; $i++) {
            $order = new Order();
            $order->setUser($user);
            
            $customer = $customersList[array_rand($customersList)];
            $order->setRegisteredCustomer($customer);
            $order->setCustomerName($customer->getName());
            $order->setPhone($customer->getPhone());
            $order->setAddress($customer->getAddress() . ', ' . $customer->getCity());
            
            $numItems = rand(1, 5);
            $totalAmount = 0;
            
            $chosenProducts = (array) array_rand($productsList, $numItems);
            foreach ($chosenProducts as $prodIdx) {
                $product = $productsList[$prodIdx];
                $quantity = rand(1, 10);
                $price = (float)$product->getPrice();
                
                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setPrice($price);
                
                $order->addItem($orderItem);
                $this->entityManager->persist($orderItem);
                
                $totalAmount += ($price * $quantity);
            }
            
            $order->setTotal($totalAmount);
            $order->setAmountTendered($totalAmount);
            $order->setChangeDue(0);
            
            $this->entityManager->persist($order);
            
            // Add to customer totalSpent
            $customer->addTotalSpent($totalAmount);
        }

        $this->entityManager->flush();

        $io->success("Successfully seeded 100 pharmacy products, categories, 50 customers, 20 vendors, 80 vendor orders, and 120 customer orders!");

        return Command::SUCCESS;
    }
}
