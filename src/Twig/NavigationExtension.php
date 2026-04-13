<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class NavigationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
        private CacheInterface $cache,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nav_categories' => $this->cache->get('nav.categories', function (ItemInterface $item) {
                $item->expiresAfter(3600);

                return $this->categoryRepository->findBy([], ['name' => 'ASC']);
            }),
            'nav_featured_products' => $this->cache->get('nav.featured_products', function (ItemInterface $item) {
                $item->expiresAfter(900);

                return $this->productRepository->findBy(['active' => true], ['stock' => 'DESC'], 5);
            }),
        ];
    }
}
