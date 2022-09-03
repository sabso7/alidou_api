<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Event;
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

class ApiEventController extends AbstractController
{
    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    #[Route('/api/event', name: 'app_api_event')]
    public function getAllEvents(ManagerRegistry $doctrine, SerializerInterface $serializer): Response
    {
        $events = $doctrine->getRepository(Event::class)->findAll();
        $events = $serializer->serialize($events, 'json',['groups' => ['event', 'user']]);

        return new Response(
            $events
        );
    }

    #[Route('/api/event/{id}', name: 'app_api_one_event')]
    public function getOneEvent(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        $event = $doctrine->getRepository(Event::class)->find($id);
        $event = $serializer->serialize($event, 'json',['groups' => ['event', 'user']]);

        return new Response(
            $event
        );
    }

    #[Route('/api/new-event', name: 'app_api_new_event')]
    public function addEvent(ManagerRegistry $doctrine, Request $request, FileUploader $fileUploader): JsonResponse
    {
        //get info from the token
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());

        $entityManager = $doctrine->getManager();
        $newEvent = new Event;

        $author = $doctrine->getRepository(User::class)->findOneBy(['email' => $decodedJwtToken['email']]);

        $title = $request->request->get('title');
        $image = $request->files->get('image');
        $description = $request->request->get('description');

        if($image){
            $imageName = $fileUploader->upload($image, 'images');
            $newEvent->setImage($imageName);
        }
        $newEvent->setDescription($description);
        $newEvent->setAuthor($author);
        $newEvent->setTitle($title);

        $entityManager->persist($newEvent);
        $entityManager->flush();

        return $this->json([
            'event' => 'good'
        ]);
    }

    #[Route('/api/remove-event/{id}', name: 'app_api_remove_event')]
    public function removeEvent(ManagerRegistry $doctrine, FileUploader $fileUploader, int $id): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());
        $eventToRemove = $doctrine->getRepository(Event::class)->find($id);

        if($eventToRemove->getAuthor()->getEmail() !== $decodedJwtToken['email']){
            return $this->json([
                'error' => "this event doesn't belong to you !"
            ]);
        };
        $fileUploader->delete('/images/'.$eventToRemove->getImage());
        $doctrine->getRepository(Event::class)->remove($eventToRemove,true);

        return $this->json([
            'event' => 'event '.$eventToRemove->getTitle().' is removed'
        ]);
    }

}
