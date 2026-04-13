<?php

namespace App\Entity;

use App\Repository\OrdersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: OrdersRepository::class)]
#[ORM\Table(name: 'orders')]
class Orders
{
    const STATUS_NEW        = 'NOUVELLE';
    const STATUS_VALIDATED  = 'VALIDEE';
    const STATUS_PREPARING  = 'EN_PREPARATION';
    const STATUS_READY      = 'PRETE_A_RETIRER';
    const STATUS_COLLECTED  = 'RETIREE';
    const STATUS_CANCELLED  = 'ANNULEE';

    const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_VALIDATED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_COLLECTED,
        self::STATUS_CANCELLED,
    ];

    const STATUS_LABELS = [
        self::STATUS_NEW       => 'Nouvelle',
        self::STATUS_VALIDATED => 'Validée',
        self::STATUS_PREPARING => 'En préparation',
        self::STATUS_READY     => 'Prête à retirer',
        self::STATUS_COLLECTED => 'Retirée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $orderNumber;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $slot = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrderNumber(): string { return $this->orderNumber; }
    public function setOrderNumber(string $v): static { $this->orderNumber = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $v): static { $this->firstName = $v; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $v): static { $this->lastName = $v; return $this; }
    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $v): static { $this->comment = $v; return $this; }
    public function getTotal(): string { return $this->total; }
    public function setTotal(string|float $v): static { $this->total = (string)$v; return $this; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(OrderItem $item): static { if (!$this->items->contains($item)) { $this->items->add($item); $item->setOrder($this); } return $this; }
    public function removeItem(OrderItem $item): static { $this->items->removeElement($item); return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }
    public function getSlot(): ?string { return $this->slot; }
    public function setSlot(?string $v): static { $this->slot = $v; return $this; }
    public function recalculateTotal(): void
{
    $sum = 0.0;
    foreach ($this->items as $it) {
        $sum += $it->getSubtotal();
    }
    $this->total = number_format($sum, 2, '.', '');
}
}
