<?php

namespace App\Controller\Admin;

use App\Repository\CartRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        OrdersRepository $orders,
        ProductRepository $products,
        UserRepository $users,
        CartRepository $carts
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $kpis = [
            'orders_new' => $orders->countByStatus(\App\Entity\Orders::STATUS_NEW),
            'orders_today' => $orders->countCreatedSince(new \DateTimeImmutable('today')),
            'products_active' => $products->count(['active' => true]),
            'users_total' => $users->count([]),
            'abandoned_carts' => $carts->countAbandoned(),
        ];

        $latestOrders = $orders->findBy([], ['createdAt' => 'DESC'], 8);

        return $this->render('admin/dashboard.html.twig', [
            'kpis' => $kpis,
            'latestOrders' => $latestOrders,
        ]);
    }
}