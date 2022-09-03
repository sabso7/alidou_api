<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiRegisterController extends AbstractController
{
    #[Route('/api/register', name: 'app_api_register')]
    public function register(ManagerRegistry $doctrine, ValidatorInterface $validator, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $entityManager = $doctrine->getManager();
        $user = new User();
        $user->setEmail($request->request->get("email"));
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $request->request->get("password")
        );
        $user->setPassword($hashedPassword);
        $user->setUsername($request->request->get("username"));
        $user->setRoles(['ROLE_ADMIN']);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse((string) $errors, 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Your are well registered !',
        ]);
    }
}
