<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/products', name: 'admin_products_')]
class ProductController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, ProductRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $products = $qb->getQuery()->getResult();
        $activeCount = 0;
        foreach ($products as $product) {
            if ($product->isActive()) {
                ++$activeCount;
            }
        }

        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'q' => $q,
            'activeCount' => $activeCount,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleProductImageUpload($form->get('imageFile')->getData(), $product, $slugger);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Produit cree.');

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/form.html.twig', [
            'form' => $form,
            'product' => $product,
            'title' => 'Nouveau produit',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ((bool) $form->get('removePhoto')->getData()) {
                $this->removeProductImageFile($product->getPhoto());
                $product->setPhoto(null);
            }

            $this->handleProductImageUpload($form->get('imageFile')->getData(), $product, $slugger);

            $em->flush();
            $this->addFlash('success', 'Produit mis a jour.');

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('admin/product/form.html.twig', [
            'form' => $form,
            'product' => $product,
            'title' => 'Modifier produit',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('del_product_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');

            return $this->redirectToRoute('admin_products_index');
        }

        $this->removeProductImageFile($product->getPhoto());

        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Produit supprime.');

        return $this->redirectToRoute('admin_products_index');
    }

    private function handleProductImageUpload(?UploadedFile $imageFile, Product $product, SluggerInterface $slugger): void
    {
        if (!$imageFile) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $filesystem = new Filesystem();
        $filesystem->mkdir($uploadDir);

        $safeName = (string) $slugger->slug($product->getName() ?: 'product');
        $extension = $imageFile->guessExtension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', strtolower($safeName), substr(bin2hex(random_bytes(4)), 0, 8), $extension);

        $imageFile->move($uploadDir, $filename);

        $currentPhoto = $this->normalizeStoredPhoto($product->getPhoto());

        if ($currentPhoto && $currentPhoto !== $filename) {
            $this->removeProductImageFile($currentPhoto);
        }

        $product->setPhoto($filename);
    }

    private function removeProductImageFile(?string $filename): void
    {
        $normalizedFilename = $this->normalizeStoredPhoto($filename);

        if (!$normalizedFilename) {
            return;
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/products/' . $normalizedFilename;
        $filesystem = new Filesystem();

        if ($filesystem->exists($path)) {
            $filesystem->remove($path);
        }
    }

    private function normalizeStoredPhoto(?string $photo): ?string
    {
        if (!$photo) {
            return null;
        }

        $photo = trim(str_replace('\\', '/', $photo));

        if (str_contains($photo, '/')) {
            $parts = explode('/', $photo);
            $photo = end($parts) ?: null;
        }

        return $photo ?: null;
    }
}
