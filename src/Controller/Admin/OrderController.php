<?php

namespace App\Controller\Admin;

use App\Entity\OrderItem;
use App\Entity\Orders;
use App\Form\Admin\OrderAddItemType;
use App\Form\Admin\OrderStatusType;
use App\Repository\OrdersRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\GuestToCustomerService;
use App\Service\OrderWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/orders', name: 'admin_orders_')]
class OrderController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, OrdersRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        $qb = $repo->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('o.status = :s')->setParameter('s', $status);
        }

        $orders = $qb->getQuery()->getResult();

        return $this->render('admin/order/index.html.twig', [
            'orders' => $orders,
            'status' => $status,
            'statuses' => Orders::STATUS_LABELS,
        ]);
    }

   #[Route('/new', name: 'new')]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/order/new_order.html.twig');
    }
    #[Route('/search/clients', name: 'search_clients')]
public function searchClients(Request $request, UserRepository $userRepo): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    $q = trim((string) $request->query->get('q', ''));

    if (strlen($q) < 2) {
        return new JsonResponse(['results' => []]);
    }

    // Adapte le champ selon ton entité User (firstName/lastName ou name)
    $users = $userRepo->createQueryBuilder('u')
        ->where('LOWER(u.firstName) LIKE LOWER(:q) OR LOWER(u.lastName) LIKE LOWER(:q) OR LOWER(u.email) LIKE LOWER(:q)')
        ->setParameter('q', '%' . $q . '%')
        ->setMaxResults(10)
        ->getQuery()
        ->getResult();

    $results = array_map(fn($u) => [
        'id'        => $u->getId(),
        'firstName' => $u->getFirstName(),
        'lastName'  => $u->getLastName(),
        'email'     => $u->getEmail(),
    ], $users);

    return new JsonResponse(['results' => $results]);
}


// ── 2) Recherche produits (AJAX) ──────────────────────────────────────────────
//
// GET /admin/orders/search/products?q=salade
//
// ⚠ Même remarque : doit être avant /{id}
//
#[Route('/search/products', name: 'search_products')]
public function searchProducts(Request $request, ProductRepository $products): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    $q = trim((string) $request->query->get('q', ''));

    if (strlen($q) < 2) {
        return new JsonResponse(['results' => []]);
    }

    $list = $products->createQueryBuilder('p')
        ->where('LOWER(p.name) LIKE LOWER(:q) OR LOWER(p.slug) LIKE LOWER(:q)')
        ->andWhere('p.active = true')   // ou isActive() selon ton champ
        ->setParameter('q', '%' . $q . '%')
        ->setMaxResults(12)
        ->getQuery()
        ->getResult();

    $results = array_map(fn($p) => [
        'id'    => $p->getId(),
        'name'  => $p->getName(),
        'slug'  => $p->getSlug(),
        'price' => $p->getPrice(),
        'stock' => $p->getStock(),
    ], $list);

    return new JsonResponse(['results' => $results]);
}


// ── 3) Créer une commande complète (AJAX) ─────────────────────────────────────
//
// POST /admin/orders/create
// Body JSON : { isGuest, client, guest:{firstName,lastName,email,phone}, items:[{id,quantity}], slot, comment, _token }
//
// ⚠ Doit aussi être avant /{id}
//
#[Route('/create', name: 'create', methods: ['POST'])]
public function create(
    Request $request,
    EntityManagerInterface $em,
    ProductRepository $products,
    UserRepository $userRepo,        // adapte selon ton archi
    GuestToCustomerService $guestToCustomer
): JsonResponse {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    if (!$request->isXmlHttpRequest()) {
        return new JsonResponse(['error' => 'Requête invalide.'], 400);
    }

    $payload = json_decode($request->getContent(), true);

    // CSRF
    if (!$this->isCsrfTokenValid('new_order_ajax', $payload['_token'] ?? '')) {
        return new JsonResponse(['error' => 'CSRF invalide.'], 403);
    }

    // ── Client / Guest ────────────────────────────────────────────
    $order = new Orders();
    $order->setOrderNumber('BO-' . strtoupper(bin2hex(random_bytes(4))));
    $order->setTotal('0.00');

    if (!empty($payload['isGuest'])) {
        // Guest sans compte
        $guest = $payload['guest'] ?? [];
        $firstName = trim($guest['firstName'] ?? '');
        $lastName  = trim($guest['lastName']  ?? '');

        if (!$firstName || !$lastName) {
            return new JsonResponse(['error' => 'Prénom et nom requis pour un invité.'], 422);
        }

        $order->setFirstName($firstName);
        $order->setLastName($lastName);
        $order->setEmail($guest['email'] ?? null);
        $order->setPhone($guest['phone'] ?? null);
    } else {
        // Client existant
        $clientId = $payload['client'] ?? null;
        if (!$clientId) {
            return new JsonResponse(['error' => 'Aucun client sélectionné.'], 422);
        }

        $user = $userRepo->find($clientId);
        if (!$user) {
            return new JsonResponse(['error' => 'Client introuvable.'], 404);
        }

        $order->setFirstName($user->getFirstName());
        $order->setLastName($user->getLastName());
        $order->setEmail($user->getEmail());
        // Adapte si tu as setUser() ou setCustomer() :
        // $order->setUser($user);
    }

    // ── Slot + comment ────────────────────────────────────────────
    if (!empty($payload['slot']))    $order->setSlot($payload['slot']);
    if (!empty($payload['comment'])) $order->setComment($payload['comment']);

    // ── Items ─────────────────────────────────────────────────────
    $items = $payload['items'] ?? [];
    if (empty($items)) {
        return new JsonResponse(['error' => 'La commande doit contenir au moins un article.'], 422);
    }

    foreach ($items as $row) {
        $product = $products->find($row['id'] ?? 0);
        if (!$product || !$product->isActive()) {
            return new JsonResponse(['error' => 'Produit introuvable ou inactif : ' . ($row['id'] ?? '?')], 422);
        }

        $qty = max(1, (int)($row['quantity'] ?? 1));

        if ($product->getStock() < $qty) {
            return new JsonResponse([
                'error' => 'Stock insuffisant pour "' . $product->getName() . '" (stock : ' . $product->getStock() . ')'
            ], 422);
        }

        $item = new OrderItem();
        $item->setOrder($order);
        $item->setProduct($product);
        $item->setProductName($product->getName());
        $item->setUnitPrice($product->getPrice());
        $item->setQuantity($qty);

        $order->addItem($item);
        $em->persist($item);

        // Réserve le stock
        $product->setStock($product->getStock() - $qty);
    }

    $order->recalculateTotal();

    $em->persist($order);
    $em->flush();

    return new JsonResponse(['ok' => true, 'id' => $order->getId()]);
}

    #[Route('/{id}', name: 'show')]
    public function show(Orders $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/order/show.html.twig', ['order' => $order, 'statuses' => Orders::STATUS_LABELS, ]);
    }

    #[Route('/{id}/status', name: 'status')]
    public function status(
        Request $request,
        Orders $order,
        OrderWorkflow $workflow,
        GuestToCustomerService $guestToCustomer,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $allowed = $workflow->nextStatuses($order->getStatus());

        $form = $this->createForm(OrderStatusType::class, $order, [
            'choices' => $allowed,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $new = $order->getStatus();
            $old = $request->request->all('order_status')['__old'] ?? null;

            // sécurité: vérifier transition depuis l'ancien statut
            $from = $old ?: $order->getStatus(); // fallback
            if (!$workflow->canTransition($from, $new)) {
                $this->addFlash('danger', 'Transition interdite.');
                return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
            }

            // si validation: créer user à partir de l’order
            if ($new === Orders::STATUS_VALIDATED) {
                $guestToCustomer->ensureUserFromOrder($order);
            }

            $em->flush();
            $this->addFlash('success', 'Statut mis à jour.');
            return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
        }

        return $this->render('admin/order/status.html.twig', [
            'order' => $order,
            'form' => $form,
            'allowed' => $allowed,
        ]);
    }
#[Route('/{id}/status/ajax', name: 'status_ajax', methods: ['POST'])]
public function statusAjax(
    Request $request,
    Orders $order,
    OrderWorkflow $workflow,
    GuestToCustomerService $guestToCustomer,
    EntityManagerInterface $em
): JsonResponse {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    if (!$request->isXmlHttpRequest()) {
        return new JsonResponse(['error' => 'Requête invalide.'], 400);
    }

    $payload = json_decode($request->getContent(), true);
    $newStatus = $payload['status'] ?? null;
    $token     = $payload['_token'] ?? '';

    // Vérif CSRF
    if (!$this->isCsrfTokenValid('order_status_ajax', $token)) {
        return new JsonResponse(['error' => 'CSRF invalide.'], 403);
    }

    $oldStatus = $order->getStatus();

// 1) sécurité: statut cible doit exister
if (!in_array($newStatus, Orders::STATUSES, true)) {
    return new JsonResponse(['error' => 'Statut inconnu.'], 400);
}

// 2) si la commande est NOUVELLE => on garde la logique workflow (pas de saut)
if ($oldStatus === Orders::STATUS_NEW) {
    if (!$workflow->canTransition($oldStatus, $newStatus)) {
        return new JsonResponse([
            'error' => sprintf(
                'Transition "%s" → "%s" non autorisée.',
                Orders::STATUS_LABELS[$oldStatus] ?? $oldStatus,
                Orders::STATUS_LABELS[$newStatus] ?? $newStatus
            )
        ], 422);
    }
} else {
    // 3) à partir de VALIDEE (et après), on autorise tout SAUF retour en NOUVELLE
    if ($newStatus === Orders::STATUS_NEW) {
        return new JsonResponse(['error' => 'Retour à "Nouvelle" interdit.'], 422);
    }
}
    // Appliquer le statut
    $order->setStatus($newStatus);

    // Si validation : créer l'utilisateur depuis la commande (guest → customer)
    if ($newStatus === Orders::STATUS_VALIDATED) {
        $guestToCustomer->ensureUserFromOrder($order);
    }

    $em->flush();

    return new JsonResponse([
        'ok'        => true,
        'newStatus' => $newStatus,
        'label'     => Orders::STATUS_LABELS[$newStatus] ?? $newStatus,
    ]);
}
    #[Route('/{id}/items/add', name: 'add_item')]
public function addItem(
    Request $request,
    Orders $order,
    EntityManagerInterface $em,
    ProductRepository $products
): Response {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    // petit guard: si commande annulée/retirée on peut bloquer
    if (in_array($order->getStatus(), [\App\Entity\Orders::STATUS_CANCELLED, \App\Entity\Orders::STATUS_COLLECTED], true)) {
        $this->addFlash('warning', 'Impossible de modifier une commande annulée/retirée.');
        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }

    $form = $this->createForm(OrderAddItemType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        /** @var \App\Entity\Product $product */
        $product = $form->get('product')->getData();
        $qty = (int)$form->get('quantity')->getData();

        if (!$product->isActive()) {
            $this->addFlash('warning', 'Ce produit est inactif.');
            return $this->redirectToRoute('admin_orders_add_item', ['id' => $order->getId()]);
        }

        // stock check soft (tu peux rendre ça strict)
        if ($product->getStock() < $qty) {
            $this->addFlash('warning', 'Stock insuffisant (stock: '.$product->getStock().').');
            return $this->redirectToRoute('admin_orders_add_item', ['id' => $order->getId()]);
        }

        // si le produit existe déjà dans la commande => on incrémente
        $existing = null;
        foreach ($order->getItems() as $it) {
            if ($it->getProduct() && $it->getProduct()->getId() === $product->getId()) {
                $existing = $it; break;
            }
        }

        if ($existing) {
            $existing->setQuantity($existing->getQuantity() + $qty);
        } else {
            $item = new OrderItem();
            $item->setOrder($order);
            $item->setProduct($product);
            $item->setProductName($product->getName());
            $item->setUnitPrice($product->getPrice());
            $item->setQuantity($qty);

            $order->addItem($item);
            $em->persist($item);
        }

        // décrémente stock (si tu veux que BO réserve réellement)
        $product->setStock($product->getStock() - $qty);

        // total
        $order->recalculateTotal();

        $em->flush();

        $this->addFlash('success', 'Article ajouté, total recalculé.');
        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }

    return $this->render('admin/order/add_item.html.twig', [
        'order' => $order,
        'form' => $form,
    ]);
}

#[Route('/{orderId}/items/{itemId}/delete', name: 'delete_item', methods: ['POST'])]
public function deleteItem(
    Request $request,
    int $orderId,
    int $itemId,
    EntityManagerInterface $em
): Response {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    $order = $em->getRepository(\App\Entity\Orders::class)->find($orderId);
    $item  = $em->getRepository(\App\Entity\OrderItem::class)->find($itemId);

    if (!$order || !$item || $item->getOrder()->getId() !== $order->getId()) {
        $this->addFlash('danger', 'Item introuvable.');
        return $this->redirectToRoute('admin_orders_index');
    }

    if (!$this->isCsrfTokenValid('del_order_item_'.$item->getId(), (string)$request->request->get('_token'))) {
        $this->addFlash('danger', 'CSRF invalide.');
        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }

    // remet stock si item lié à un produit
    if ($item->getProduct()) {
        $p = $item->getProduct();
        $p->setStock($p->getStock() + $item->getQuantity());
    }

    $em->remove($item);

    // recalcul total
    $order->recalculateTotal();

    $em->flush();

    $this->addFlash('success', 'Article supprimé, total recalculé.');
    return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
}

#[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
public function delete(
    Request $request,
    Orders $order,
    EntityManagerInterface $em
): JsonResponse {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    if (!$request->isXmlHttpRequest()) {
        return new JsonResponse(['error' => 'Requête invalide.'], 400);
    }

    $payload = json_decode($request->getContent(), true);
    $token   = $payload['_token'] ?? '';

    if (!$this->isCsrfTokenValid('delete_order_' . $order->getId(), $token)) {
        return new JsonResponse(['error' => 'CSRF invalide.'], 403);
    }

    // Seules les commandes "pending" (nouvelles) peuvent être supprimées
    if ($order->getStatus() !== Orders::STATUS_NEW) {
        return new JsonResponse(['error' => 'Seules les commandes nouvelles peuvent être supprimées.'], 422);
    }

    // Restitue le stock pour chaque article
    foreach ($order->getItems() as $item) {
        if ($item->getProduct()) {
            $item->getProduct()->setStock(
                $item->getProduct()->getStock() + $item->getQuantity()
            );
        }
        $em->remove($item);
    }

    $em->remove($order);
    $em->flush();

    return new JsonResponse(['ok' => true]);
}

}