<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\UserDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserDocumentRepository::class)]
#[ORM\Table(name: 'user_documents')]
class UserDocument
{
    use UuidEntityTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['passport', 'id_card', 'driving_license'])]
    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: false)]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $fileUrl = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pending', 'verified', 'rejected'])]
    #[ORM\Column(length: 50)]
    private ?string $verificationStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getVerificationStatus(): ?string
    {
        return $this->verificationStatus;
    }

    public function setVerificationStatus(string $verificationStatus): static
    {
        $this->verificationStatus = $verificationStatus;

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
