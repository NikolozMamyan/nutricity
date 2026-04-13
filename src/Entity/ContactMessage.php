<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactMessage
{
    #[Assert\NotBlank(message: 'Votre nom est requis.')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    #[Assert\NotBlank(message: 'Votre email est requis.')]
    #[Assert\Email(message: 'Email invalide.')]
    #[Assert\Length(max: 120)]
    private ?string $email = null;

    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    #[Assert\NotBlank(message: 'Sujet requis.')]
    #[Assert\Length(max: 120)]
    private ?string $subject = null;

    #[Assert\NotBlank(message: 'Votre message est requis.')]
    #[Assert\Length(min: 10, minMessage: 'Votre message doit faire au moins {{ limit }} caractères.')]
    #[Assert\Length(max: 2000)]
    private ?string $message = null;

    // getters/setters
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $subject): self { $this->subject = $subject; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }
}