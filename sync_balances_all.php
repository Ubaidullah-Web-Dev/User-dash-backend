<?php
require __DIR__.'/vendor/autoload.php';
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use App\Entity\RegisteredCustomer;
use App\Entity\Order;

(new Dotenv())->bootEnv(__DIR__.'/.env');
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$customers = $em->getRepository(RegisteredCustomer::class)->findAll();

foreach ($customers as $customer) {
    $currentBalance = $customer->getRemainingBalance();
    
    // Get all unpaid orders
    $orders = $em->getRepository(Order::class)->createQueryBuilder('o')
        ->where('o.registeredCustomer = :customer')
        ->andWhere('o.changeDue < 0')
        ->setParameter('customer', $customer)
        ->orderBy('o.createdAt', 'ASC')
        ->getQuery()
        ->getResult();
        
    $totalOrderPending = 0;
    foreach ($orders as $order) {
        $totalOrderPending += abs($order->getChangeDue());
    }
    
    if ($totalOrderPending > $currentBalance) {
        $paymentAmount = $totalOrderPending - $currentBalance;
        foreach ($orders as $order) {
            $orderPending = abs($order->getChangeDue());
            if ($paymentAmount >= $orderPending) {
                $order->setChangeDue(0);
                $order->setAmountTendered($order->getAmountTendered() + $orderPending);
                $paymentAmount -= $orderPending;
            } else {
                $order->setChangeDue(-($orderPending - $paymentAmount));
                $order->setAmountTendered($order->getAmountTendered() + $paymentAmount);
                $paymentAmount = 0;
                break;
            }
        }
        echo "Synchronized orders for customer " . $customer->getName() . "\n";
    }
}
$em->flush();
echo "Done sync all.\n";
