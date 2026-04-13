<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products', name: 'admin_products_')]
class ProductController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, ProductRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string)$request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')->setParameter('q', '%'.$q.'%');
        }

        return $this->render('admin/product/index.html.twig', [
            'products' => $qb->getQuery()->getResult(),
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $p = new Product();
        $form = $this->createForm(ProductType::class, $p);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($p);
            $em->flush();
            $this->addFlash('success', 'Produit créé.');
            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau produit',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Product $p, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProductType::class, $p);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Produit mis à jour.');
            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier produit',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Product $p, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('del_product_'.$p->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');
            return $this->redirectToRoute('admin_products_index');
        }

        $em->remove($p);
        $em->flush();
        $this->addFlash('success', 'Produit supprimé.');
        return $this->redirectToRoute('admin_products_index');
    }
}