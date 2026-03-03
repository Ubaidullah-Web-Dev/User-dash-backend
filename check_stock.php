<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\Product;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

$productRepository = $entityManager->getRepository(Product::class);
$products = $productRepository->findAll();

foreach ($products as $p) {
    echo sprintf("ID: %d, Name: %s, Stock: %d\n", $p->getId(), $p->getName(), $p->getStock());
}