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
        if (!$order || $order->getUser() !== $user) {
            return $this->json(['message' => 'Order not found or unauthorized'], Response::HTTP_NOT_FOUND);
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

        $logoData = '';
        /*
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        */

        $html = $this->renderView('invoice/invoice.html.twig', [
            'logo' => $logoData,
            'poNumber' => 'PO-' . str_pad($order->getId(), 3, '0', STR_PAD_LEFT),
            'date' => $order->getCreatedAt(),
            'customer' => [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'address' => $order->getAddress(),
                'phone' => $order->getPhone(),
            ],
            'items' => $itemsData,
            'subtotal' => $order->getTotal(),
            'total' => $order->getTotal(), // Simplifying for now, subtotal == total in order entity
            'paid' => $order->getTotal(),
            'balance' => 0,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="PO-%s.pdf"', $order->getId()),
        ]);
    }
}