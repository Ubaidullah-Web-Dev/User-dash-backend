<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    #[Route('', name: 'order_create', methods: ['POST'])]
    public function create(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['address']) || !isset($data['phone']) || !isset($data['name'])) {
            return $this->json(['message' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        // Backend Validation
        if (strlen($data['name']) < 2 || is_numeric($data['name'])) {
            return $this->json(['message' => 'Invalid name format'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($data['address']) < 5 || is_numeric($data['address'])) {
            return $this->json(['message' => 'Invalid address format'], Response::HTTP_BAD_REQUEST);
        }

        $phoneDigits = preg_replace('/\D/', '', $data['phone']);
        if (strlen($phoneDigits) > 11 || empty($phoneDigits)) {
            return $this->json(['message' => 'Phone number must be maximum 11 digits'], Response::HTTP_BAD_REQUEST);
        }

        $cartItems = $cartItemRepository->findBy(['user' => $user]);
        if (empty($cartItems)) {
            return $this->json(['message' => 'Cart is empty'], Response::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setUser($user);
        $order->setAddress($data['address']);
        $order->setPhone($data['phone']);
        $order->setCustomerName($data['name'] ?? $user->getName());
        
        $total = 0;
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            
            // Final Stock Check
            if ($product->getStock() < $cartItem->getQuantity()) {
                return $this->json([
                    'message' => sprintf('Insufficient stock for %s. Only %d left.', $product->getName(), $product->getStock())
                ], Response::HTTP_BAD_REQUEST);
            }

            $itemTotal = $product->getPrice() * $cartItem->getQuantity();
            $total += $itemTotal;

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($product->getPrice());
            $order->addItem($orderItem);

            // Deduct Stock
            $product->setStock($product->getStock() - $cartItem->getQuantity());

            $entityManager->remove($cartItem);
        }

        $order->setTotal($total);
        $entityManager->persist($order);
        $entityManager->flush();

        return $this->json([
            'message' => 'Order created successfully',
            'id' => $order->getId()
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'order_show', methods: ['GET'])]
    public function show(Order $order): JsonResponse
    {
        if ($order->getUser() !== $this->getUser()) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'productName' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice()
            ];
        }

        return $this->json([
            'id' => $order->getId(),
            'address' => $order->getAddress(),
            'phone' => $order->getPhone(),
            'total' => $order->getTotal(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'items' => $items
        ]);
    }
}