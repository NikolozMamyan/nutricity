<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $em): void
    {
        $admin = new User();
        $admin->setEmail('admin@gmail.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('Backoffice');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setNeedsPasswordSetup(false);

        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));

        $em->persist($admin);
        $em->flush();
    }
}