<?php

namespace App\Entity;

use App\Enum\DocumentStatut;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DocumentVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $utilisateur;

    #[ORM\Column(length: 60)]
    public string $typeDocument = 'carte_identite';

    #[ORM\Column(length: 255)]
    public string $fichierUrl = '';

    #[ORM\Column(enumType: DocumentStatut::class)]
    public DocumentStatut $statut = DocumentStatut::EN_ATTENTE;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $motifRefus = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateUpload;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateValidation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $validePar = null;

    public function __construct()
    {
        $this->dateUpload = new \DateTimeImmutable();
    }
}
