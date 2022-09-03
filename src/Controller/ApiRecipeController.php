<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Recipe;
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

class ApiRecipeController extends AbstractController
{
    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    #[Route('/api/recipe', name: 'app_api_recipe')]
    public function getAllRecipes(ManagerRegistry $doctrine, SerializerInterface $serializer): Response
    {
        $recipes = $doctrine->getRepository(Recipe::class)->findAll();
        $recipes = $serializer->serialize($recipes, 'json',['groups' => ['recipe', 'user']]);

        return new Response(
            $recipes
        );
    }

    #[Route('/api/recipe/{id}', name: 'app_api_one_recipe')]
    public function getOneRecipe(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        $recipe = $doctrine->getRepository(Recipe::class)->find($id);
        $recipe = $serializer->serialize($recipe, 'json',['groups' => ['recipe', 'user']]);

        return new Response(
            $recipe
        );
    }

    #[Route('/api/new-recipe', name: 'app_api_new_recipe')]
    public function addRecipe(ManagerRegistry $doctrine, Request $request, FileUploader $fileUploader): JsonResponse
    {
        //get info from the token
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());

        $entityManager = $doctrine->getManager();
        $newRecipe = new Recipe;

        $author = $doctrine->getRepository(User::class)->findOneBy(['email' => $decodedJwtToken['email']]);

        $title = $request->request->get('title');
        $image = $request->files->get('image');
        $description = $request->request->get('description');

        if($image){
            $imageName = $fileUploader->upload($image, 'images');
            $newRecipe->setImage($imageName);
        }
        $newRecipe->setDescription($description);
        $newRecipe->setAuthor($author);
        $newRecipe->setTitle($title);

        $entityManager->persist($newRecipe);
        $entityManager->flush();

        return $this->json([
            'recipe' => 'good'
        ]);
    }

    #[Route('/api/remove-recipe/{id}', name: 'app_api_remove_recipe')]
    public function removeRecipe(ManagerRegistry $doctrine, FileUploader $fileUploader, int $id): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());
        $recipeToRemove = $doctrine->getRepository(recipe::class)->find($id);

        if($recipeToRemove->getAuthor()->getEmail() !== $decodedJwtToken['email']){
            return $this->json([
                'error' => "this recipe doesn't belong to you !"
            ]);
        };
        $fileUploader->delete('/images/'.$recipeToRemove->getImage());
        $doctrine->getRepository(recipe::class)->remove($recipeToRemove,true);

        return $this->json([
            'recipe' => 'recipe '.$recipeToRemove->getTitle().' is removed'
        ]);
    }

}
