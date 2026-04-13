<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Origines "Europe de l'Est" (exemples)
        $origins = ['Pologne', 'Roumanie', 'Bulgarie', 'Ukraine', 'Géorgie', 'Lituanie', 'Lettonie', 'Serbie'];

        // 80 produits par défaut (ajuste)
        $count = 80;

        for ($i = 1; $i <= $count; $i++) {
            $p = new Product();

            // Nom "réaliste"
            $base = $faker->randomElement([
                'Cornichons', 'Sarrasin', 'Kefir', 'Ajvar', 'Tarama', 'Halva', 'Blinis', 'Pelmeni',
                'Kvas', 'Compote', 'Paprika fumé', 'Chocolat', 'Bonbons', 'Thé noir', 'Moutarde', 'Saucisson',
            ]);
            $name = $base . ' ' . $faker->words($faker->numberBetween(1, 3), true);

            $p->setName($name);

            // Slug : si ton projet le génère automatiquement, tu peux supprimer ces 2 lignes
            if (method_exists($p, 'setSlug')) {
                $p->setSlug($this->slugify($name . '-' . $i));
            }

            // Prix en EUR (ex: 1.20 à 19.90)
            $price = $faker->randomFloat(2, 1.20, 19.90);
            $p->setPrice($price);

            // Stock
            $stock = $faker->numberBetween(0, 50);
            $p->setStock($stock);

            // Active + isNew
            if (method_exists($p, 'setActive')) {
                $p->setActive($faker->boolean(90)); // 90% visibles
            }
            if (method_exists($p, 'setIsNew')) {
                $p->setIsNew($faker->boolean(25)); // 25% "nouveaux"
            }

            // Origine
            if (method_exists($p, 'setOrigin')) {
                $p->setOrigin($faker->randomElement($origins));
            }

            // Descriptions
            if (method_exists($p, 'setShortDescription')) {
                $p->setShortDescription($faker->sentence(14));
            }
            if (method_exists($p, 'setLongDescription')) {
                // Si tu utilises du HTML (CKEditor), tu peux garder une string simple ou mettre un mini HTML
                $p->setLongDescription('<p>'.$faker->paragraph(4).'</p><p>'.$faker->paragraph(4).'</p>');
            }

            // SEO
            if (method_exists($p, 'setMetaTitle')) {
                $p->setMetaTitle(mb_substr($name . ' - NUTRI CITY', 0, 255));
            }
            if (method_exists($p, 'setMetaDescription')) {
                $p->setMetaDescription(mb_substr($faker->sentence(18), 0, 160));
            }

            // Photo (simple nom de fichier)
            // Tes templates lisent product.photo et font asset('uploads/products/' ~ product.photo)
            if (method_exists($p, 'setPhoto')) {
                $p->setPhoto($faker->randomElement([
                    'placeholder-1.jpg',
                    'placeholder-2.jpg',
                    'placeholder-3.jpg',
                    'placeholder-4.jpg',
                ]));
            }

            // Catégorie (aléatoire)
            $catIndex = $faker->numberBetween(0, count(CategoryFixtures::REFS) - 1);
           $category = $this->getReference('cat_'.$catIndex, Category::class);

            if (method_exists($p, 'setCategory')) {
                $p->setCategory($category);
            }

            $manager->persist($p);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
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
