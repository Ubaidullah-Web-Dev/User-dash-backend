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
use App\DTO\OrderCreateDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    #[Route('', name: 'order_create', methods: ['POST'])]
    public function create(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        $dto = new OrderCreateDTO();
        $dto->name = $data['name'] ?? null;
        $dto->address = $data['address'] ?? null;
        $dto->phone = $data['phone'] ?? null;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $phoneDigits = preg_replace('/\D/', '', $dto->phone);
        if (strlen($phoneDigits) > 11 || empty($phoneDigits)) {
            return $this->json(['message' => 'Phone number must contain at most 11 digits'], Response::HTTP_BAD_REQUEST);
        }

        $cartItems = $cartItemRepository->findBy(['user' => $user]);
        if (empty($cartItems)) {
            return $this->json(['message' => 'Cart is empty'], Response::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setUser($user);
        $order->setAddress($dto->address);
        $order->setPhone($dto->phone);
        $order->setCustomerName($dto->name);
        
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