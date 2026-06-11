<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\ReviewMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewMediaRepository::class)]
#[ORM\Table(name: 'review_media')]
class ReviewMedia
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'L\'avis associé est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Review $review = null;

    #[Assert\NotBlank(message: 'L\'URL du fichier est obligatoire.')]
    #[Assert\Url(
        message: 'Le lien du fichier n\'est pas une URL valide.',
        protocols: ['http', 'https'],
    )]
    #[Assert\Length(max: 2048, maxMessage: 'L\'URL du fichier ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $fileUrl = null;

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;

        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }
}
