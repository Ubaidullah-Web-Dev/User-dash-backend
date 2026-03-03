<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\Vendor;
use App\Entity\Category;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

$categoryRepository = $entityManager->getRepository(Category::class);
$categories = $categoryRepository->findAll();

if (empty($categories)) {
    echo "No categories found. Please seed categories first.\n";
    exit(1);
}

$sampleVendors = [
    [
        'name' => 'TechSupply Pro',
        'email' => 'contact@techsupply.com',
        'company' => 'TechSupply Solutions Ltd.',
        'phone' => '+1234567890',
        'category' => 'Laptops'
    ],
    [
        'name' => 'MobileHub Global',
        'email' => 'sales@mobilehub.net',
        'company' => 'MobileHub Global Networks',
        'phone' => '+0987654321',
        'category' => 'Smartphones'
    ],
    [
        'name' => 'Elite Access',
        'email' => 'info@eliteaccess.com',
        'company' => 'Elite Accessories Corp.',
        'phone' => '+1122334455',
        'category' => 'Accessories'
    ]
];

foreach ($sampleVendors as $vData) {
    // Find category by name
    $category = null;
    foreach ($categories as $cat) {
        if (stripos($cat->getName(), $vData['category']) !== false) {
            $category = $cat;
            break;
        }
    }

    if (!$category)
        continue;

    $existing = $entityManager->getRepository(Vendor::class)->findOneBy(['email' => $vData['email']]);
    if ($existing)
        continue;

    $vendor = new Vendor();
    $vendor->setName($vData['name']);
    $vendor->setEmail($vData['email']);
    $vendor->setCompanyName($vData['company']);
    $vendor->setPhone($vData['phone']);
    $vendor->setCategory($category);
    $vendor->setStatus('active');
    $vendor->setAddress("123 Vendor Street, Supply District");

    $entityManager->persist($vendor);
}

$entityManager->flush();
echo "Sample vendors created successfully!\n";