<?php

namespace App\Controller;

use App\Entity\GlobalSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/super-admin')]
class SuperAdminController extends AbstractController
{
    #[Route('/settings', name: 'super_admin_get_settings', methods: ['GET'])]
    public function getSettings(EntityManagerInterface $entityManager): JsonResponse
    {
        $settings = $entityManager->getRepository(GlobalSetting::class)->findAll();
        $data = [];
        foreach ($settings as $setting) {
            $data[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        return $this->json($data);
    }

    #[Route('/settings', name: 'super_admin_update_settings', methods: ['POST'])]
    public function updateSettings(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['message' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data as $key => $value) {
            $setting = $entityManager->getRepository(GlobalSetting::class)->findOneBy(['settingKey' => $key]);
            if (!$setting) {
                $setting = new GlobalSetting();
                $setting->setSettingKey($key);
            }
            $setting->setSettingValue((string) $value);
            $entityManager->persist($setting);
        }

        $entityManager->flush();

        return $this->json(['message' => 'Settings updated successfully']);
    }

    #[Route('/companies', name: 'super_admin_list_companies', methods: ['GET'])]
    public function listCompanies(EntityManagerInterface $entityManager): JsonResponse
    {
        $companies = $entityManager->getRepository(\App\Entity\Company::class)->findAll();
        return $this->json($companies);
    }

    #[Route('/companies', name: 'super_admin_create_company', methods: ['POST'])]
    public function createCompany(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['name']) || !isset($data['slug'])) {
            return $this->json(['message' => 'Missing name or slug'], Response::HTTP_BAD_REQUEST);
        }

        $company = new \App\Entity\Company();
        $company->setName($data['name']);
        $company->setSlug($data['slug']);
        $company->setSettingsJson($data['settings'] ?? []);

        $entityManager->persist($company);
        $entityManager->flush();

        return $this->json($company, Response::HTTP_CREATED);
    }

    #[Route('/companies/{id}', name: 'super_admin_update_company', methods: ['PUT'])]
    public function updateCompany(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $company = $entityManager->getRepository(\App\Entity\Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) $company->setName($data['name']);
        if (isset($data['slug'])) $company->setSlug($data['slug']);
        if (isset($data['settings'])) $company->setSettingsJson($data['settings']);

        $entityManager->flush();

        return $this->json($company);
    }

    #[Route('/companies/{id}', name: 'super_admin_delete_company', methods: ['DELETE'])]
    public function deleteCompany(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $company = $entityManager->getRepository(\App\Entity\Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if it's the default company
        if ($company->getId() === 1) {
            return $this->json(['message' => 'Cannot delete default company'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->remove($company);
        $entityManager->flush();

        return $this->json(['message' => 'Company deleted successfully']);
    }

    #[Route('/companies/{id}/admins', name: 'super_admin_create_company_admin', methods: ['POST'])]
    public function createCompanyAdmin(
        int $id, 
        Request $request, 
        EntityManagerInterface $entityManager,
        \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $company = $entityManager->getRepository(\App\Entity\Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            return $this->json(['message' => 'Missing email, password or name'], Response::HTTP_BAD_REQUEST);
        }

        $user = new \App\Entity\User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_COMPANY_ADMIN']);
        $user->setCompany($company);
        
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'Company admin created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/companies/{id}/toggle-banner', name: 'super_admin_toggle_company_banner', methods: ['POST'])]
    public function toggleCompanyBanner(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $company = $entityManager->getRepository(\App\Entity\Company::class)->find($id);
        if (!$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $settings = $company->getSettingsJson() ?? [];
        $data = json_decode($request->getContent(), true);
        
        $settings['show_alphasoft_banner'] = (isset($data['enabled']) && $data['enabled']) ? '1' : '0';
        $company->setSettingsJson($settings);

        $entityManager->flush();

        return $this->json(['message' => 'Banner setting updated', 'settings' => $settings]);
    }

    #[Route('/public/settings', name: 'public_get_settings', methods: ['GET'])]
    public function getPublicSettings(EntityManagerInterface $entityManager): JsonResponse
    {
        // Add keys that are allowed to be public
        $publicKeys = ['show_alphasoft_banner'];
        
        $data = [];
        foreach ($publicKeys as $key) {
            $setting = $entityManager->getRepository(GlobalSetting::class)->findOneBy(['settingKey' => $key]);
            $data[$key] = $setting ? $setting->getSettingValue() : '0';
        }

        return $this->json($data);
    }

    #[Route('/admins', name: 'super_admin_create_admin', methods: ['POST'])]
    public function createSuperAdmin(
        Request $request, 
        EntityManagerInterface $entityManager,
        \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            return $this->json(['message' => 'Missing email, password or name'], Response::HTTP_BAD_REQUEST);
        }

        // Find the system company (ID 1)
        $company = $entityManager->getRepository(\App\Entity\Company::class)->find(1);
        if (!$company) {
            return $this->json(['message' => 'System company not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user = new \App\Entity\User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setCompany($company);
        
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'New Super Admin entry provisioned successfully'], Response::HTTP_CREATED);
    }
}
