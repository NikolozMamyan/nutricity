<?php

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    #[Route('/navigation/category-preview/{slug}', name: 'category_preview', methods: ['GET'])]
    public function preview(string $slug, CategoryRepository $categoryRepo, ProductRepository $productRepo): JsonResponse
    {
        $category = $categoryRepo->findOneBy(['slug' => $slug]);
        if (!$category) {
            return new JsonResponse(['success' => false], 404);
        }

        $products = $productRepo->findBy(['category' => $category, 'active' => true], ['createdAt' => 'DESC'], 6);

        return new JsonResponse([
            'success' => true,
            'category' => [
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
                'url' => $this->generateUrl('category_show', ['slug' => $category->getSlug()]),
            ],
            'items' => array_map(static fn ($product) => [
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'price' => number_format((float) $product->getPrice(), 2, ',', ' '),
                'image' => $product->getPhoto(),
            ], $products),
        ]);
    }

    #[Route('/categorie/{slug}', name: 'category_show')]
    public function show(string $slug, Request $request, CategoryRepository $categoryRepo, ProductRepository $productRepo): Response
    {
        $category = $categoryRepo->findOneBy(['slug' => $slug]);
        if (!$category) {
            throw $this->createNotFoundException('Categorie non trouvee.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $productRepo->paginateActiveByCategory($category, $page, 12);

        return $this->render('front/category/show.html.twig', [
            'category' => $category,
            'products' => $pagination['items'],
            'page' => $pagination['page'],
            'totalPages' => $pagination['totalPages'],
            'total' => $pagination['total'],
        ]);
    }
}
