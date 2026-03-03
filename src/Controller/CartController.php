<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\DTO\CartItemDTO;
use App\DTO\CartItemAddDTO;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/cart')]
class CartController extends AbstractController
{
    #[Route('', name: 'cart_index', methods: ['GET'])]
    public function index(CartItemRepository $cartItemRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $cartItems = $cartItemRepository->findBy(['user' => $user]);
        $data = array_map(fn(CartItem $item) => CartItemDTO::fromEntity($item), $cartItems);

        return $this->json($data);
    }

    #[Route('', name: 'cart_add', methods: ['POST'])]
    public function add(
        Request $request, 
        ProductRepository $productRepository, 
        CartItemRepository $cartItemRepository, 
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            /** @var CartItemAddDTO $addItemDto */
            $addItemDto = $serializer->deserialize($request->getContent(), CartItemAddDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($addItemDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $product = $productRepository->find($addItemDto->product_id);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $cartItem = $cartItemRepository->findOneBy([
            'user' => $user, 
            'product' => $product, 
            'isSavedForLater' => $addItemDto->is_saved_for_later
        ]);

        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + $addItemDto->quantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($addItemDto->quantity);
            $cartItem->setIsSavedForLater($addItemDto->is_saved_for_later);
            $entityManager->persist($cartItem);
        }

        $entityManager->flush();

        return $this->json(['message' => 'Item added to cart']);
    }

    #[Route('/{id}', name: 'cart_remove', methods: ['DELETE'])]
    public function remove(CartItem $cartItem, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($cartItem->getUser() !== $this->getUser()) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $entityManager->remove($cartItem);
        $entityManager->flush();

        return $this->json(['message' => 'Item removed']);
    }

    #[Route('/{id}/toggle-save', name: 'cart_toggle_save', methods: ['PATCH'])]
    public function toggleSave(CartItem $cartItem, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($cartItem->getUser() !== $this->getUser()) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $cartItem->setIsSavedForLater(!$cartItem->isSavedForLater());
        $entityManager->flush();

        return $this->json(['message' => $cartItem->isSavedForLater() ? 'Saved for later' : 'Moved to cart']);
    }
}