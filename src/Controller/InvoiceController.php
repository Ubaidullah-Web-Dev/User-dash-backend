<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/invoice')]
class InvoiceController extends AbstractController
{
    #[Route('/download', name: 'invoice_download', methods: ['GET'])]
    public function download(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $orderId = $request->query->get('orderId');
        if (!$orderId) {
            return $this->json(['message' => 'Order ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $order = $entityManager->getRepository(Order::class)->find($orderId);
        if (!$order) {
            return $this->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if admin or owner
        if (!$this->isGranted('ROLE_ADMIN') && $order->getUser() !== $user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $itemsData = [];
        foreach ($order->getItems() as $item) {
            $itemsData[] = [
                'description' => $item->getProduct()->getName(),
                'qty' => $item->getQuantity(),
                'rate' => $item->getPrice(),
                'amount' => $item->getPrice() * $item->getQuantity(),
            ];
        }

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoData = '';
        if (extension_loaded('gd') && file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $html = $this->renderView('invoice/invoice.html.twig', [
            'type' => 'seller',
            'logo' => $logoData,
            'poNumber' => str_pad($order->getId(), 6, '0', STR_PAD_LEFT),
            'date' => $order->getCreatedAt(),
            'customer' => [
                'name' => $order->getCustomerName() ?? ($order->getRegisteredCustomer() ? $order->getRegisteredCustomer()->getName() : 'Walk-In Customer'),
                'email' => $order->getRegisteredCustomer() ? $order->getRegisteredCustomer()->getEmail() : '',
                'address' => $order->getAddress(),
                'phone' => $order->getPhone(),
            ],
            'items' => $itemsData,
            'subtotal' => $order->getTotal() + ($order->getDiscountAmount() ?? 0),
            'total' => $order->getTotal(),
            'paid' => $order->getTotal(),
            'discountPercentage' => $order->getDiscountPercentage(),
            'discountAmount' => $order->getDiscountAmount(),
            'amountTendered' => $order->getAmountTendered(),
            'changeDue' => $order->getChangeDue(),
            'remainingBalance' => $order->getRegisteredCustomer() ? $order->getRegisteredCustomer()->getRemainingBalance() : 0,
            'balance' => 0,
        ]);

        return $this->generatePdfResponse($html, sprintf('PO-%s.pdf', $order->getId()));
    }

    #[Route('/buyer/download', name: 'invoice_buyer_download', methods: ['GET'])]
    public function downloadBuyer(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $vendorOrderId = $request->query->get('vendorOrderId');
        if (!$vendorOrderId) {
            return $this->json(['message' => 'Vendor Order ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $vendorOrder = $entityManager->getRepository(\App\Entity\VendorOrder::class)->find($vendorOrderId);
        if (!$vendorOrder) {
            return $this->json(['message' => 'Vendor Order not found'], Response::HTTP_NOT_FOUND);
        }

        $itemsData = [];
        $itemsData[] = [
            'description' => $vendorOrder->getProduct()->getName(),
            'qty' => $vendorOrder->getQuantity(),
            'rate' => $vendorOrder->getProduct()->getPrice(),
            'amount' => $vendorOrder->getProduct()->getPrice() * $vendorOrder->getQuantity(),
        ];

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoData = '';
        if (extension_loaded('gd') && file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $html = $this->renderView('invoice/invoice.html.twig', [
            'type' => 'buyer',
            'logo' => $logoData,
            'poNumber' => 'VO-' . str_pad($vendorOrder->getId(), 3, '0', STR_PAD_LEFT),
            'date' => $vendorOrder->getCreatedAt(),
            'vendor' => [
                'name' => $vendorOrder->getVendor()->getName(),
                'email' => $vendorOrder->getVendor()->getEmail(),
                'address' => $vendorOrder->getVendor()->getAddress(),
                'phone' => $vendorOrder->getVendor()->getPhone(),
            ],
            'items' => $itemsData,
            'subtotal' => $itemsData[0]['amount'],
            'total' => $itemsData[0]['amount'],
            'paid' => 0,
            'balance' => $itemsData[0]['amount'],
        ]);

        return $this->generatePdfResponse($html, sprintf('VO-%s.pdf', $vendorOrder->getId()));
    }

    private function generatePdfResponse(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}