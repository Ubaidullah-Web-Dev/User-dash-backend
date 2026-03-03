<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\DTO\ProfileUpdateDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'profile_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(UserDTO::fromEntity($user));
    }

    #[Route('/update', name: 'profile_update', methods: ['PUT'])]
    public function update(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            /** @var ProfileUpdateDTO $profileUpdateDto */
            $profileUpdateDto = $serializer->deserialize($request->getContent(), ProfileUpdateDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($profileUpdateDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        
        if ($profileUpdateDto->name !== null) {
            $user->setName($profileUpdateDto->name);
        }

        if ($profileUpdateDto->password !== null && !empty($profileUpdateDto->password)) {
            $user->setPassword($passwordHasher->hashPassword($user, $profileUpdateDto->password));
        }

        $entityManager->flush();

        return $this->json(['message' => 'Profile updated successfully']);
    }
}