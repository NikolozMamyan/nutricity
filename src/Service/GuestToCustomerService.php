<?php

namespace App\Service;

use App\Entity\Orders;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GuestToCustomerService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function ensureUserFromOrder(Orders $order): ?User
    {
        $email = $order->getEmail();
        if (!$email) return null;

        $existing = $this->users->findOneBy(['email' => $email]);
        if ($existing) return $existing;

        $u = new User();
        $u->setEmail($email);
        $u->setFirstName($order->getFirstName());
        $u->setLastName($order->getLastName());
        $u->setRoles(['ROLE_USER']);
        $u->setNeedsPasswordSetup(true);

        // Mot de passe temporaire (à remplacer par un flow mail + token si tu veux)
        $tmp = bin2hex(random_bytes(6));
        $u->setPassword($this->hasher->hashPassword($u, $tmp));

        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }
}