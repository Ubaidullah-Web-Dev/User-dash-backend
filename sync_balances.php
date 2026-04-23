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
    if ($customer->getRemainingBalance() == 0) {
        $orders = $em->getRepository(Order::class)->findBy(['registeredCustomer' => $customer]);
        foreach ($orders as $order) {
            if ($order->getChangeDue() < 0) {
                $orderPending = abs($order->getChangeDue());
                $order->setChangeDue(0);
                $order->setAmountTendered($order->getAmountTendered() + $orderPending);
                echo "Fixed order " . $order->getId() . " for customer " . $customer->getName() . "\n";
            }
        }
    }
}
$em->flush();
echo "Done.\n";
