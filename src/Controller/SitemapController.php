<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap')]
    public function index(ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
    {
        $urls = [];

        foreach (['home', 'catalogue', 'about', 'click_collect', 'contact'] as $route) {
            $urls[] = ['loc' => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.8'];
        }

        foreach ($productRepo->findBy(['active' => true]) as $p) {
            $urls[] = [
                'loc'      => $this->generateUrl('product_show', ['slug' => $p->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'  => $p->getCreatedAt()->format('Y-m-d'),
                'priority' => '0.9',
            ];
        }

        foreach ($categoryRepo->findAll() as $c) {
            $urls[] = [
                'loc'      => $this->generateUrl('category_show', ['slug' => $c->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'priority' => '0.7',
            ];
        }

        $response = new Response(
            $this->renderView('sitemap.xml.twig', ['urls' => $urls]),
            200,
            ['Content-Type' => 'application/xml']
        );

        return $response;
    }

    #[Route('/robots.txt', name: 'robots')]
    public function robots(): Response
    {
        $content = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /commande/valider\nDisallow: /commande/confirmation\nSitemap: " . $this->generateUrl('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
