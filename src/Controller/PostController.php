<?php

namespace App\Controller;

use App\Entity\Post;
use App\DTO\PostDTO;
use App\DTO\PostCreateUpdateDTO;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/posts')]
class PostController extends AbstractController
{
    #[Route('', name: 'post_index', methods: ['GET'])]
    public function index(PostRepository $postRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('POST_VIEW');
        
        $posts = $postRepository->findAll();
        $data = array_map(fn(Post $post) => PostDTO::fromEntity($post), $posts);

        return $this->json($data);
    }

    #[Route('', name: 'post_create', methods: ['POST'])]
    public function create(
        Request $request, 
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('POST_CREATE');

        try {
            /** @var PostCreateUpdateDTO $postDto */
            $postDto = $serializer->deserialize($request->getContent(), PostCreateUpdateDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($postDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $post = new Post();
        $post->setTitle($postDto->title);
        $post->setContent($postDto->content);
        $post->setAuthor($this->getUser());

        $entityManager->persist($post);
        $entityManager->flush();

        return $this->json(['message' => 'Post created successfully', 'id' => $post->getId()]);
    }

    #[Route('/{id}', name: 'post_edit', methods: ['PUT'])]
    public function edit(
        Post $post, 
        Request $request, 
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse
    {
        // Use the dynamic 'POST_EDIT' attribute which the Voter handles
        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        try {
            /** @var PostCreateUpdateDTO $postDto */
            $postDto = $serializer->deserialize($request->getContent(), PostCreateUpdateDTO::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['message' => 'Invalid JSON input'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($postDto);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $post->setTitle($postDto->title);
        $post->setContent($postDto->content);

        $entityManager->flush();

        return $this->json(['message' => 'Post updated successfully']);
    }

    #[Route('/{id}', name: 'post_delete', methods: ['DELETE'])]
    public function delete(Post $post, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('POST_DELETE');

        $entityManager->remove($post);
        $entityManager->flush();

        return $this->json(['message' => 'Post deleted successfully']);
    }
}