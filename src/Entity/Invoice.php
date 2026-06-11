<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidEntityTrait;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class Invoice
{
    use UuidEntityTrait;

    #[Assert\NotNull(message: 'La réservation associée est obligatoire.')]
    #[ORM\OneToOne(inversedBy: 'invoice', targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[Assert\NotBlank(message: 'Le numéro de facture est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro de facture ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(length: 50, unique: true)]
    private ?string $invoiceNumber = null;

    #[Assert\Url(
        message: 'Le lien du PDF n\'est pas une URL valide.',
        protocols: ['http', 'https'],
    )]
    #[Assert\Length(max: 2048, maxMessage: 'L\'URL du PDF ne peut pas dépasser {{ limit }} caractères.')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pdfUrl = null;

    #[Assert\NotBlank(message: 'Le montant total est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le montant total doit être supérieur ou égal à zéro.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function getPdfUrl(): ?string
    {
        return $this->pdfUrl;
    }

    public function setPdfUrl(?string $pdfUrl): static
    {
        $this->pdfUrl = $pdfUrl;

        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

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
