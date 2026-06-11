<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Enum\UserStatut;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(enumType: UserRole::class)]
    public UserRole $role = UserRole::VOYAGEUR;

    #[ORM\Column(length: 100)]
    public string $prenom = '';

    #[ORM\Column(length: 100)]
    public string $nom = '';

    #[ORM\Column(length: 180, unique: true)]
    public string $email = '';

    #[ORM\Column(length: 30, nullable: true)]
    public ?string $telephone = null;

    #[ORM\Column(length: 255)]
    public string $motDePasseHash = '';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $photoProfil = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\ManyToOne(targetEntity: Langue::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Langue $langue = null;

    #[ORM\ManyToOne(targetEntity: Devise::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code')]
    public ?Devise $devise = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $pays = null;

    #[ORM\Column(enumType: UserStatut::class)]
    public UserStatut $statut = UserStatut::EN_ATTENTE_VERIFICATION;

    #[ORM\Column]
    public bool $emailVerifie = false;

    #[ORM\Column]
    public bool $telephoneVerifie = false;

    #[ORM\Column]
    public bool $identiteVerifiee = false;

    #[ORM\Column]
    public bool $consentementCgu = false;

    #[ORM\Column]
    public bool $consentementMarketing = false;

    #[ORM\Column]
    public \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dateDerniereConnexion = null;

    #[ORM\OneToOne(mappedBy: 'utilisateur', targetEntity: ProfilUtilisateur::class, cascade: ['persist', 'remove'])]
    public ?ProfilUtilisateur $profil = null;

    /** @var Collection<int, Logement> */
    #[ORM\OneToMany(mappedBy: 'hote', targetEntity: Logement::class)]
    public Collection $logements;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(mappedBy: 'voyageur', targetEntity: Reservation::class)]
    public Collection $reservationsVoyageur;

    /** @var Collection<int, Favori> */
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Favori::class, orphanRemoval: true)]
    public Collection $favoris;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->logements = new ArrayCollection();
        $this->reservationsVoyageur = new ArrayCollection();
        $this->favoris = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([
            $this->role->value,
            'ROLE_USER',
        ]));
    }

    public function getPassword(): string
    {
        return $this->motDePasseHash;
    }

    public function eraseCredentials(): void
    {
    }
}
