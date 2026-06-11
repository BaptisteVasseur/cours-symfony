<?php

namespace App\Entity;

use App\Enum\DisponibiliteStatut;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_disponibilite_logement_date', columns: ['logement_id', 'date'])]
class Disponibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Logement::class, inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: false)]
    public Logement $logement;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public \DateTimeImmutable $date;

    #[ORM\Column(enumType: DisponibiliteStatut::class)]
    public DisponibiliteStatut $statut = DisponibiliteStatut::DISPONIBLE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    public ?string $prixSpecifique = null;

    #[ORM\Column]
    public int $sejourMinimum = 1;

    #[ORM\Column(nullable: true)]
    public ?int $sejourMaximum = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $raisonBlocage = null;

    #[ORM\Column]
    public \DateTimeImmutable $dateMiseAJour;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
        $this->dateMiseAJour = new \DateTimeImmutable();
    }
}
