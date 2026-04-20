<?php

namespace App\Controller;

use App\Entity\ResetPasswordToken;
use App\Entity\User;
use App\DTO\ForgotPasswordDTO;
use App\DTO\VerifyCodeDTO;
use App\DTO\ResetPasswordDTO;
use App\Repository\UserRepository;
use App\Repository\ResetPasswordTokenRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        CompanyRepository $companyRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ?string $companySlug = null
    ): JsonResponse {
        $company = null;
        if ($companySlug) {
            $company = $companyRepository->findOneBy(['slug' => $companySlug]);
        }

        if ($companySlug && !$company) {
            return $this->json(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            /** @var ForgotPasswordDTO $forgotPasswordDto */
            $forgotPasswordDto = $serializer->deserialize($request->getContent(), ForgotPasswordDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($forgotPasswordDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $forgotPasswordDto->email, 'company' => $company]);

        if (!$user) {
            return $this->json(['message' => 'No account found with this email. Please register first.'], Response::HTTP_NOT_FOUND);
        }

        // Generate 6-digit numeric code
        $code = (string) random_int(100000, 999999);

        // Remove old tokens for this user
        $oldTokens = $entityManager->getRepository(ResetPasswordToken::class)->findBy(['user' => $user]);
        foreach ($oldTokens as $oldToken) {
            $entityManager->remove($oldToken);
        }

        $resetToken = new ResetPasswordToken();
        $resetToken->setUser($user);
        $resetToken->setCompany($company);
        $resetToken->setToken($code); // Using token field for the 6-digit code
        $resetToken->setExpiresAt(new \DateTimeImmutable('+15 minutes')); // OTPs expire faster

        $entityManager->persist($resetToken);
        $entityManager->flush();

        // Send email (via SMTP)
        $emailMessage = (new Email())
            ->from('ubaidullah.web.dev@gmail.com')
            ->to($user->getEmail())
            ->subject('Your Password Reset Code')
            ->html("<p>Hi " . $user->getName() . ",</p><p>Your password reset code is: <strong>" . $code . "</strong></p><p>This code expires in 15 minutes.</p>");

        $mailer->send($emailMessage);

        $logger->info("RESET PASSWORD CODE for " . $user->getEmail() . ": " . $code);

        return $this->json(['message' => 'If your email is in our system, you will receive a 6-digit code shortly.'], Response::HTTP_OK);
    }

    #[Route('/api/verify-code', name: 'api_verify_code', methods: ['POST'])]
    public function verifyCode(
        Request $request,
        ResetPasswordTokenRepository $tokenRepository,
        UserRepository $userRepository,
        CompanyRepository $companyRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ?string $companySlug = null
    ): JsonResponse {
        $company = null;
        if ($companySlug) {
            $company = $companyRepository->findOneBy(['slug' => $companySlug]);
        }

        try {
            /** @var VerifyCodeDTO $verifyCodeDto */
            $verifyCodeDto = $serializer->deserialize($request->getContent(), VerifyCodeDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($verifyCodeDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $verifyCodeDto->email, 'company' => $company]);
        if (!$user) {
            return $this->json(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $tokenRepository->findOneBy(['token' => $verifyCodeDto->code, 'user' => $user, 'company' => $company]);

        if (!$resetToken || $resetToken->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Invalid or expired code'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Code verified successfully'], Response::HTTP_OK);
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        ResetPasswordTokenRepository $tokenRepository,
        UserRepository $userRepository,
        CompanyRepository $companyRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ?string $companySlug = null
    ): JsonResponse {
        $company = null;
        if ($companySlug) {
            $company = $companyRepository->findOneBy(['slug' => $companySlug]);
        }

        try {
            /** @var ResetPasswordDTO $resetPasswordDto */
            $resetPasswordDto = $serializer->deserialize($request->getContent(), ResetPasswordDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($resetPasswordDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $resetPasswordDto->email, 'company' => $company]);
        if (!$user) {
            return $this->json(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $tokenRepository->findOneBy(['token' => $resetPasswordDto->code, 'user' => $user, 'company' => $company]);

        if (!$resetToken || $resetToken->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Invalid or expired code'], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $resetPasswordDto->password);
        $user->setPassword($hashedPassword);

        // Explicitly persist and flush
        $entityManager->persist($user);
        $entityManager->remove($resetToken);
        $entityManager->flush();

        return $this->json(['message' => 'Password reset successfully'], Response::HTTP_OK);
    }
}