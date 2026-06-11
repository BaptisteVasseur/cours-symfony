<?php

namespace App\Entity;

use App\Repository\ReviewPhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReviewPhotoRepository::class)]
class ReviewPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Review $review = null;

    #[ORM\Column(type: 'text')]
    private ?string $imageUrl = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getReview(): ?Review { return $this->review; }
    public function setReview(?Review $v): static { $this->review = $v; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(string $v): static { $this->imageUrl = $v; return $this; }
}
