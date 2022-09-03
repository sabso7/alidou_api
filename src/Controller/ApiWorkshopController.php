<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workshop;
use App\Service\FileUploader;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ApiWorkshopController extends AbstractController
{
    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    #[Route('/api/workshop', name: 'app_api_workshop')]
    public function getAllWorkshops(ManagerRegistry $doctrine, SerializerInterface $serializer): Response
    {
        $workshops = $doctrine->getRepository(Workshop::class)->findAll();
        $workshops = $serializer->serialize($workshops, 'json',['groups' => ['workshop', 'user']]);

        return new Response(
            $workshops
        );
    }

    #[Route('/api/workshop/{id}', name: 'app_api_one_workshop')]
    public function getOneWorkshop(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        $workshop = $doctrine->getRepository(Workshop::class)->find($id);
        $workshop = $serializer->serialize($workshop, 'json',['groups' => ['workshop', 'user']]);

        return new Response(
            $workshop
        );
    }

    #[Route('/api/new-workshop', name: 'app_api_new_workshop')]
    public function addWorkshop(ManagerRegistry $doctrine, Request $request, FileUploader $fileUploader): JsonResponse
    {
        //get info from the token
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());

        $entityManager = $doctrine->getManager();
        $newWorkshop = new Workshop;

        $author = $doctrine->getRepository(User::class)->findOneBy(['email' => $decodedJwtToken['email']]);

        $title = $request->request->get('title');
        $image = $request->files->get('image');
        $description = $request->request->get('description');

        if($image){
            $imageName = $fileUploader->upload($image, 'images');
            $newWorkshop->setImage($imageName);
        }
        $newWorkshop->setDescription($description);
        $newWorkshop->setAuthor($author);
        $newWorkshop->setTitle($title);

        $entityManager->persist($newWorkshop);
        $entityManager->flush();

        return $this->json([
            'workshop' => 'good'
        ]);
    }

    #[Route('/api/remove-workshop/{id}', name: 'app_api_remove_workshop')]
    public function removeWorkshop(ManagerRegistry $doctrine, FileUploader $fileUploader, int $id): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());
        $workshopToRemove = $doctrine->getRepository(workshop::class)->find($id);

        if($workshopToRemove->getAuthor()->getEmail() !== $decodedJwtToken['email']){
            return $this->json([
                'error' => "this workshop doesn't belong to you !"
            ]);
        };
        $fileUploader->delete('/images/'.$workshopToRemove->getImage());
        $doctrine->getRepository(workshop::class)->remove($workshopToRemove,true);

        return $this->json([
            'workshop' => 'workshop '.$workshopToRemove->getTitle().' is removed'
        ]);
    }

}
