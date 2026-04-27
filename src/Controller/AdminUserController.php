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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\DTO\AdminCreateUserDTO;

#[Route('/api/admin')]
class AdminUserController extends AbstractController
{
    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    public function listUsers(
        Request $request, 
        UserRepository $userRepository,
        \App\Service\TenantContext $tenantContext
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('USER_VIEW');

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $filters = [
            'search' => $request->query->get('search'),
            'role' => $request->query->get('role'),
            'companyId' => $tenantContext->getCurrentCompanyId(),
            'excludeSuperAdmin' => true,
        ];

        $paginatedResponse = $userRepository->getPaginatedUsers($filters, $page, $limit);

        return $this->json([
            'data' => array_map(fn($user) => UserDTO::fromEntity($user), $paginatedResponse->data),
            'total' => $paginatedResponse->total,
            'page' => $paginatedResponse->page,
            'pages' => $paginatedResponse->pages,
            'limit' => $paginatedResponse->limit,
        ]);
    }

    #[Route('/users/{id}/role', name: 'admin_user_update_roles', methods: ['PATCH'])]
    public function updateRoles(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        \App\Service\TenantContext $tenantContext
    ): JsonResponse {
        $this->denyAccessUnlessGranted('USER_EDIT_ROLE');

        $user = $userRepository->find($id);
        if (!$user || $user->getCompany()?->getId() !== $tenantContext->getCurrentCompanyId()) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Prevent modifying Super Admin roles by regular admins
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return $this->json(['message' => 'Access denied: Cannot modify Super Admin roles'], Response::HTTP_FORBIDDEN);
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

    #[Route('/users/{id}', name: 'admin_user_delete', methods: ['DELETE'])]
    public function deleteUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        \App\Service\TenantContext $tenantContext
    ): JsonResponse {
        $this->denyAccessUnlessGranted('USER_DELETE');

        $user = $userRepository->find($id);
        if (!$user || $user->getCompany()?->getId() !== $tenantContext->getCurrentCompanyId()) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Safety: Cannot delete self
        if ($user === $this->getUser()) {
            return $this->json(['message' => 'Security protocol violation: Self-deletion is restricted'], Response::HTTP_FORBIDDEN);
        }

        // Safety: Cannot delete Super Admin
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return $this->json(['message' => 'Access denied: Super Admin deletion restricted'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return $this->json(['message' => 'User database entry purged successfully']);
    }

    #[Route('/users', name: 'admin_user_create', methods: ['POST'])]
    public function createUser(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        \App\Service\TenantContext $tenantContext
    ): JsonResponse {
        $this->denyAccessUnlessGranted('USER_CREATE');

        try {
            /** @var AdminCreateUserDTO $createUserDto */
            $createUserDto = $serializer->deserialize($request->getContent(), AdminCreateUserDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        if (!$createUserDto) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($createUserDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $createUserDto->email]);
        if ($existingUser) {
            return $this->json(['message' => 'Email already in use'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($createUserDto->email);
        $user->setName($createUserDto->name);
        
        $roles = $createUserDto->roles ?? ['ROLE_USER'];
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }
        $user->setRoles(array_unique($roles));
        
        $hashedPassword = $passwordHasher->hashPassword($user, $createUserDto->password);
        $user->setPassword($hashedPassword);

        // Set the company from the context (resolved from URL slug)
        $company = $tenantContext->getCurrentCompany();
        if (!$company) {
            return $this->json(['message' => 'Could not determine company context'], Response::HTTP_BAD_REQUEST);
        }
        $user->setCompany($company);

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['message' => 'User creation failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'User created successfully',
            'user' => UserDTO::fromEntity($user)
        ], Response::HTTP_CREATED);
    }
}