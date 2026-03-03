<?php

namespace App\DTO;

use App\Entity\Post;

class PostDTO
{
    public int $id;
    public string $title;
    public string $content;
    public string $author;
    public string $authorEmail;

    public static function fromEntity(Post $post): self
    {
        $dto = new self();
        $dto->id = $post->getId();
        $dto->title = $post->getTitle() ?: 'Untitled';
        $dto->content = $post->getContent() ?: '';
        $dto->author = $post->getAuthor()?->getName() ?: 'Anonymous';
        $dto->authorEmail = $post->getAuthor()?->getEmail() ?: '';

        return $dto;
    }
}