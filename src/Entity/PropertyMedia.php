<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\PropertyMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PropertyMediaRepository::class)]
#[ORM\Table(name: 'property_media')]
class PropertyMedia
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'Le logement associé est obligatoire.')]
    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[Assert\NotBlank(message: 'Le type de média est obligatoire.')]
    #[Assert\Choice(
        choices: ['image', 'video'],
        message: 'Le type de média sélectionné n\'est pas valide.',
    )]
    #[ORM\Column(length: 50)]
    private ?string $mediaType = null;

    #[Assert\NotBlank(message: 'L\'URL du fichier est obligatoire.')]
    #[Assert\Url(
        message: 'Le lien du fichier n\'est pas une URL valide.',
        protocols: ['http', 'https'],
    )]
    #[Assert\Length(max: 2048, maxMessage: 'L\'URL du fichier ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $fileUrl = null;

    #[Assert\PositiveOrZero(message: 'L\'ordre d\'affichage doit être supérieur ou égal à zéro.')]
    #[ORM\Column]
    private int $sortOrder = 0;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column]
    private bool $isCover = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): static
    {
        $this->mediaType = $mediaType;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isCover(): bool
    {
        return $this->isCover;
    }

    public function setIsCover(bool $isCover): static
    {
        $this->isCover = $isCover;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
