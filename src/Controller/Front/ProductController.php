<?php

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/produits', name: 'catalogue')]
    public function catalogue(Request $request, ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
    {
        $search     = $request->query->get('q');
        $categoryId = $request->query->get('category');
        $page       = max(1, (int)$request->query->get('page', 1));
        $perPage    = 12;

        $qb = $productRepo->createQueryBuilder('p')
            ->where('p.active = true')
            ->orderBy('p.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('p.name LIKE :q OR p.shortDescription LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($categoryId) {
            $qb->andWhere('p.category = :cat')->setParameter('cat', $categoryId);
        }

        $total    = (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $products = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('front/product/catalogue.html.twig', [
            'products'   => $products,
            'categories' => $categoryRepo->findAll(),
            'search'     => $search,
            'categoryId' => $categoryId,
            'page'       => $page,
            'totalPages' => ceil($total / $perPage),
        ]);
    }

    #[Route('/produit/{slug}', name: 'product_show')]
    public function show(string $slug, ProductRepository $productRepo): Response
    {
        $product = $productRepo->findOneBy(['slug' => $slug, 'active' => true]);
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé.');
        }

        $related = $productRepo->findBy(
            ['category' => $product->getCategory(), 'active' => true],
            null, 4
        );

        return $this->render('front/product/show.html.twig', [
            'product' => $product,
            'related' => array_filter($related, fn($p) => $p->getId() !== $product->getId()),
        ]);
    }
}
