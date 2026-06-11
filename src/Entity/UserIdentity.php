<?php

namespace App\Entity;

use App\Repository\UserIdentityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserIdentityRepository::class)]
class UserIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\OneToOne(inversedBy: 'identity')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $documentFront = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $documentBack = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $selfiePhoto = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $verificationStatus = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }

    public function getDocumentType(): ?string { return $this->documentType; }
    public function setDocumentType(?string $v): static { $this->documentType = $v; return $this; }

    public function getDocumentFront(): ?string { return $this->documentFront; }
    public function setDocumentFront(?string $v): static { $this->documentFront = $v; return $this; }

    public function getDocumentBack(): ?string { return $this->documentBack; }
    public function setDocumentBack(?string $v): static { $this->documentBack = $v; return $this; }

    public function getSelfiePhoto(): ?string { return $this->selfiePhoto; }
    public function setSelfiePhoto(?string $v): static { $this->selfiePhoto = $v; return $this; }

    public function getVerificationStatus(): ?string { return $this->verificationStatus; }
    public function setVerificationStatus(?string $v): static { $this->verificationStatus = $v; return $this; }

    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeImmutable $v): static { $this->verifiedAt = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
}
