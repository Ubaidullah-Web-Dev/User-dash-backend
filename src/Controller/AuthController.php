<?php

namespace App\Controller;

use App\Entity\User;
use App\DTO\RegisterDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ): JsonResponse {
        try {
            /** @var RegisterDTO $registerDto */
            $registerDto = $serializer->deserialize($request->getContent(), RegisterDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        if (!$registerDto) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($registerDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ($registerDto->password !== $registerDto->confirmPassword) {
            return $this->json(['message' => 'Passwords do not match'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($registerDto->email);
        $user->setName($registerDto->name);
        $user->setRoles(['ROLE_USER']);
        
        $hashedPassword = $passwordHasher->hashPassword($user, $registerDto->password);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User registered successfully'], Response::HTTP_CREATED);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): Response
    {
        return $this->json(['message' => 'This should be handled by JWT bundle']);
    }
}