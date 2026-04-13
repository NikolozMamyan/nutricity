<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\Admin\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories', name: 'admin_categories_')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, CategoryRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string)$request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('c')->orderBy('c.name', 'ASC');

        if ($q !== '') {
            $qb->andWhere('c.name LIKE :q OR c.slug LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        return $this->render('admin/category/index.html.twig', [
            'categories' => $qb->getQuery()->getResult(),
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $c = new Category();
        $form = $this->createForm(CategoryType::class, $c);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($c);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle catégorie',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Category $c, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CategoryType::class, $c);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie mise à jour.');
            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier catégorie',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Category $c, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('del_category_'.$c->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');
            return $this->redirectToRoute('admin_categories_index');
        }

        // Option: empêcher suppression si produits associés (sinon Doctrine FK peut casser)
        if ($c->getProducts()->count() > 0) {
            $this->addFlash('warning', 'Impossible: des produits utilisent cette catégorie.');
            return $this->redirectToRoute('admin_categories_index');
        }

        $em->remove($c);
        $em->flush();
        $this->addFlash('success', 'Catégorie supprimée.');
        return $this->redirectToRoute('admin_categories_index');
    }
}