<?php

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use App\Entity\RegisteredCustomer;
use App\Entity\Order;
use App\Entity\CashRecovery;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$customers = $em->getRepository(RegisteredCustomer::class)->findAll();

echo "Starting customer balance recalculation...\n";
$correctedCount = 0;

foreach ($customers as $customer) {
    echo "Processing Customer ID {$customer->getId()} ({$customer->getName()})\n";
    
    // Calculate True Total Spent (sum of all order totals)
    $orders = $customer->getOrders();
    $trueTotalSpent = 0;
    foreach ($orders as $order) {
        $trueTotalSpent += $order->getTotal();
    }
    
    // Calculate True Total Paid (sum of initial tenders + cash recoveries)
    // To do this, we need to know the true payment made.
    // Actually, amountTendered on orders gets updated by CashRecovery,
    // so sum(amountTendered) is theoretically the total paid EXCEPT if it includes the same recovery spread out.
    // Wait, the walkInOrder logic directly updates amountTendered on unpaid orders when previousBalancePayment > 0.
    // CashRecoveryController also updates amountTendered on unpaid orders.
    // This means `amountTendered` is already the sum of all payments distributed to the order.
    // Therefore, the sum of all `amountTendered` across all orders equals the total amount paid by the customer.
    
    $trueTotalPaid = 0;
    foreach ($orders as $order) {
        $trueTotalPaid += $order->getAmountTendered() ?: 0;
    }
    
    // Wait, what if there's an unapplied payment? Cash recoveries are always applied to unpaid orders.
    // So the Remaining Balance should exactly be: True Total Spent - True Total Paid
    $trueRemainingBalance = $trueTotalSpent - $trueTotalPaid;
    
    // Let's check if the current balance matches
    $currentSpent = $customer->getTotalSpent();
    $currentBalance = $customer->getRemainingBalance();
    
    if (round($currentSpent) !== round($trueTotalSpent) || round($currentBalance) !== round($trueRemainingBalance)) {
        echo "  - Mismatch found! \n";
        echo "  - Total Spent: Current = {$currentSpent}, Correct = {$trueTotalSpent}\n";
        echo "  - Balance: Current = {$currentBalance}, Correct = {$trueRemainingBalance}\n";
        
        // Correct them
        $customer->setTotalSpent($trueTotalSpent);
        $customer->setRemainingBalance($trueRemainingBalance);
        $correctedCount++;
    }
}

$em->flush();
echo "Finished! Corrected {$correctedCount} customers.\n";
