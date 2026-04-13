<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Orders $order;

    #[ORM\ManyToOne]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $unitPrice;

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Orders { return $this->order; }
    public function setOrder(Orders $order): static { $this->order = $order; return $this; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }
    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $v): static { $this->productName = $v; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $v): static { $this->quantity = $v; return $this; }
    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string|float $v): static { $this->unitPrice = (string)$v; return $this; }
    public function getSubtotal(): float { return $this->quantity * (float)$this->unitPrice; }
}
