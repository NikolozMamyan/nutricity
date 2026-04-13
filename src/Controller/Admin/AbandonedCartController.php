<?php

namespace App\Controller\Admin;

use App\Entity\Cart;
use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/carts', name: 'admin_carts_')]
class AbandonedCartController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(CartRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $carts = $repo->findAbandoned(30);

        return $this->render('admin/cart/index.html.twig', [
            'carts' => $carts,
            'minutes' => 30,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Cart $cart): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/cart/show.html.twig', ['cart' => $cart]);
    }
}