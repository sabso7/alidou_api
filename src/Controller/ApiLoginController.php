<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'app_api_login')]
    public function index(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $doctrine->getRepository(User::class)->findOneBy(['email' => $request->request->get("email")]);

        if ($user === null || !$passwordHasher->isPasswordValid($user, $request->request->get("password"))) {
            throw new AccessDeniedHttpException();
        }

        return $this->json([
            'user' => $user->getUserIdentifier(),
        ]);
    }
}
