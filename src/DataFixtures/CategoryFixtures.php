<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CategoryFixtures extends Fixture
{
    public const REFS = [
        'Conserves',
        'Boissons',
        'Confiseries',
        'Surgelés',
        'Épices',
        'Produits laitiers',
        'Charcuterie',
        'Biscuits',
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        foreach (self::REFS as $i => $name) {
            $c = new Category();
            $c->setName($name);

            // Si ton slug est auto-généré via subscriber tu peux ignorer,
            // sinon on force un slug simple:
            if (method_exists($c, 'setSlug')) {
                $c->setSlug($this->slugify($name));
            }

            if (method_exists($c, 'setDescription')) {
                $c->setDescription($faker->sentence(12));
            }
            if (method_exists($c, 'setMetaTitle')) {
                $c->setMetaTitle($name . ' - NUTRI CITY');
            }
            if (method_exists($c, 'setMetaDescription')) {
                $c->setMetaDescription(substr($faker->sentence(18), 0, 160));
            }

            $manager->persist($c);
            $this->addReference('cat_'.$i, $c);
        }

        $manager->flush();
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('~[^\pL\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $s = preg_replace('~[^-\w]+~', '', $s);
        $s = preg_replace('~-+~', '-', $s);
        return $s ?: 'n-a';
    }
}
