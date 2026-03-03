<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

// Find testing product
$product = $entityManager->getRepository(Product::class)->findOneBy(['name' => 'Testing']);
if (!$product) {
    echo "Testing product not found\n";
    exit(1);
}

echo "Initial Stock: " . $product->getStock() . "\n";

// Find or create a test user
$user = $entityManager->getRepository(User::class)->findOneBy([]); // Just take any user
if (!$user) {
    echo "No users found\n";
    exit(1);
}

// simulate logic from OrderController
$quantityToOrder = 2;

if ($product->getStock() < $quantityToOrder) {
    echo "Insufficient stock (Expected behavior)\n";
}
else {
    $order = new Order();
    $order->setUser($user);
    $order->setAddress("Test Address");
    $order->setPhone("1234567890");
    $order->setCustomerName($user->getName());
    $order->setTotal($product->getPrice() * $quantityToOrder);

    $orderItem = new OrderItem();
    $orderItem->setProduct($product);
    $orderItem->setQuantity($quantityToOrder);
    $orderItem->setPrice($product->getPrice());
    $order->addItem($orderItem);

    // Deduct stock
    $product->setStock($product->getStock() - $quantityToOrder);

    $entityManager->persist($order);
    $entityManager->flush();

    echo "Order created. New stock: " . $product->getStock() . "\n";
}