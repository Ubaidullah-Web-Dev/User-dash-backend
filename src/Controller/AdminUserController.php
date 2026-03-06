<?php

namespace App\Controller;

use App\DTO\UserDTO;
use App\DTO\UpdateRolesDTO;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin')]
class AdminUserController extends AbstractController
{
    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    public function listUsers(UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('USER_VIEW');

        $users = $userRepository->findAll();
        $data = array_map(fn($user) => UserDTO::fromEntity($user), $users);

        return $this->json($data);
    }

    #[Route('/users/{id}/role', name: 'admin_user_update_roles', methods: ['PATCH'])]
    public function updateRoles(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        $this->denyAccessUnlessGranted('USER_EDIT_ROLE');

        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            /** @var UpdateRolesDTO $updateRolesDto */
            $updateRolesDto = $serializer->deserialize($request->getContent(), UpdateRolesDTO::class, 'json');
            $roles = $updateRolesDto->roles;
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($roles)) {
            return $this->json(['message' => 'Roles must be an array'], Response::HTTP_BAD_REQUEST);
        }

        // Basic validation: ensure ROLE_USER is always present
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }
        $roles = array_unique($roles);

        $user->setRoles($roles);
        $entityManager->flush();

        return $this->json([
            'message' => 'User roles updated successfully',
            'user' => UserDTO::fromEntity($user)
        ]);
    }
}