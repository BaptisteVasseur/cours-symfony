<?php

namespace App\Entity;

use App\Enum\CmsStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PageContenuCms
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180)]
    public string $titre = '';

    #[ORM\Column(length: 180, unique: true)]
    public string $slug = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $contenu = '';

    #[ORM\Column(enumType: CmsStatut::class)]
    public CmsStatut $statut = CmsStatut::BROUILLON;

    #[ORM\ManyToOne(targetEntity: Langue::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Langue $langue = null;

    #[ORM\Column(length: 180, nullable: true)]
    public ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $metaDescription = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $datePublication = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }
}
