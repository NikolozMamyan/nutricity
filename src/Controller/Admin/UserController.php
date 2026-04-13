<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_users_')]
class UserController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, UserRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string)$request->query->get('q', ''));
        $role = trim((string)$request->query->get('role', ''));

        $qb = $repo->createQueryBuilder('u')->orderBy('u.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.firstName LIKE :q OR u.lastName LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        // Filtre simple sur JSON roles (MySQL) - fonctionne bien sur MySQL 5.7+/8
        if ($role !== '') {
            $qb->andWhere('JSON_CONTAINS(u.roles, :r) = 1')
               ->setParameter('r', json_encode($role));
        }

        return $this->render('admin/user/index.html.twig', [
            'users' => $qb->getQuery()->getResult(),
            'q' => $q,
            'role' => $role,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $u = new User();
        $u->setRoles(['ROLE_USER']);

        $form = $this->createForm(UserType::class, $u, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string)$form->get('plainPassword')->getData();
            $u->setPassword($hasher->hashPassword($u, $plain));

            // normalise: si ROLE_ADMIN sélectionné on garde, sinon ROLE_USER auto via getRoles()
            $em->persist($u);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel utilisateur',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(
        Request $request,
        User $u,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $u, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string)$form->get('plainPassword')->getData();
            if ($plain !== '') {
                $u->setPassword($hasher->hashPassword($u, $plain));
                // si on change le mdp depuis BO, on peut lever le flag
                $u->setNeedsPasswordSetup(false);
            }

            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier utilisateur',
        ]);
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        User $u,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reset_pwd_'.$u->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');
            return $this->redirectToRoute('admin_users_index');
        }

        $tmp = bin2hex(random_bytes(6)); // 12 chars hex
        $u->setPassword($hasher->hashPassword($u, $tmp));
        $u->setNeedsPasswordSetup(true);

        $em->flush();

        // Important: on affiche le mdp temporaire à l’admin (à envoyer au client par canal sûr)
        $this->addFlash('success', 'Mot de passe réinitialisé. Temporaire: '.$tmp);

        return $this->redirectToRoute('admin_users_edit', ['id' => $u->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $u, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($u->getId() === $this->getUser()?->getId()) {
            $this->addFlash('warning', 'Tu ne peux pas supprimer ton propre compte.');
            return $this->redirectToRoute('admin_users_index');
        }

        if (!$this->isCsrfTokenValid('del_user_'.$u->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');
            return $this->redirectToRoute('admin_users_index');
        }

        $em->remove($u);
        $em->flush();

        $this->addFlash('success', 'Utilisateur supprimé.');
        return $this->redirectToRoute('admin_users_index');
    }
}