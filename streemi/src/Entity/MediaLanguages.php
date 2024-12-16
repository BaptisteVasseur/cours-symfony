<?php

namespace App\Entity;

use App\Repository\MediaLanguagesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaLanguagesRepository::class)]
class MediaLanguages
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $media_id = null;

    #[ORM\Column]
    private ?int $language_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMediaId(): ?int
    {
        return $this->media_id;
    }

    public function setMediaId(int $media_id): static
    {
        $this->media_id = $media_id;

        return $this;
    }

    public function getLanguageId(): ?int
    {
        return $this->language_id;
    }

    public function setLanguageId(int $language_id): static
    {
        $this->language_id = $language_id;

        return $this;
    }
}
