<?php

namespace App\Entity;

use App\Repository\ListingPhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ListingPhotoRepository::class)]
class ListingPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\Column(type: 'text')]
    private ?string $imageUrl = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column]
    private bool $isCover = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getListing(): ?Listing { return $this->listing; }
    public function setListing(?Listing $v): static { $this->listing = $v; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(string $v): static { $this->imageUrl = $v; return $this; }

    public function getPosition(): ?int { return $this->position; }
    public function setPosition(?int $v): static { $this->position = $v; return $this; }

    public function isCover(): bool { return $this->isCover; }
    public function setIsCover(bool $v): static { $this->isCover = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
}
