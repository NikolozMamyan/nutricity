<?php

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
    {
        $bestSellers = $productRepo->findBy(['active' => true], ['stock' => 'DESC'], 8);
        $newProducts = $productRepo->findBy(['active' => true, 'isNew' => true], null, 4);
        $categories  = $categoryRepo->findAll();

        return $this->render('front/home/index.html.twig', [
            'bestSellers' => $bestSellers,
            'newProducts' => $newProducts,
            'categories'  => $categories,
        ]);
    }
}
