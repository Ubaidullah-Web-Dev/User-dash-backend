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
}