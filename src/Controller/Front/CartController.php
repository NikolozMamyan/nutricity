<?php

namespace App\Controller\Front;

use App\Entity\OrderItem;
use App\Entity\Orders;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier', name: 'cart_')]
class CartController extends AbstractController
{
    private const SLOT_TIMES = [
        '9h00 - 10h00',
        '10h00 - 11h00',
        '11h00 - 12h00',
        '14h00 - 15h00',
        '15h00 - 16h00',
        '16h00 - 17h00',
        '17h00 - 18h00',
    ];

    #[Route('/ajouter/{id}', name: 'add', methods: ['POST'])]
    public function add(int $id, Request $request, ProductRepository $productRepo): Response
    {
        $product = $productRepo->find($id);
        if (!$product || !$product->isActive()) {
            return $this->cartError($request, 'Produit introuvable.', 404);
        }

        if ($product->getStock() <= 0) {
            return $this->cartError($request, 'Ce produit est en rupture de stock.', 409);
        }

        $session = $request->getSession();
        $cart = $this->normalizeCart($session->get('cart', []), $productRepo);
        $qty = $this->extractQuantity($request);

        $currentQty = (int) ($cart[$id]['quantity'] ?? 0);
        $newQty = min($product->getStock(), $currentQty + $qty);

        if ($newQty === $currentQty) {
            return $this->cartError($request, 'Stock maximum atteint pour ce produit.', 409);
        }

        $cart[$id] = $this->buildCartRow($product, $newQty);
        $session->set('cart', $cart);

        $totals = $this->computeCartTotals($cart);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => sprintf('"%s" ajoute au panier.', $product->getName()),
                'cartCount' => $totals['count'],
                'lineQuantity' => $newQty,
                'lineTotal' => $newQty * (float) $product->getPrice(),
            ]);
        }

        $this->addFlash('cart_success', sprintf('"%s" a ete ajoute a votre panier.', $product->getName()));

        return $this->redirect($request->headers->get('referer', $this->generateUrl('catalogue')));
    }

    #[Route('/modifier/{id}', name: 'update', methods: ['POST'])]
    public function update(int $id, Request $request, ProductRepository $productRepo): Response
    {
        $session = $request->getSession();
        $cart = $this->normalizeCart($session->get('cart', []), $productRepo);

        if (!isset($cart[$id])) {
            return $this->cartError($request, 'Article introuvable dans le panier.', 404);
        }

        $rawQty = $this->extractRawQuantity($request);
        if ($rawQty === null) {
            return $this->cartError($request, 'Quantite manquante.', 400);
        }

        $qty = max(0, (int) $rawQty);
        $product = $productRepo->find($id);

        if (!$product || !$product->isActive() || $product->getStock() <= 0) {
            unset($cart[$id]);
        } elseif ($qty === 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = $this->buildCartRow($product, min($qty, $product->getStock()));
        }

        $session->set('cart', $cart);
        $totals = $this->computeCartTotals($cart);

        if ($request->isXmlHttpRequest()) {
            $subtotal = isset($cart[$id])
                ? (float) $cart[$id]['price'] * (int) $cart[$id]['quantity']
                : 0.0;

            return new JsonResponse([
                'success' => true,
                'subtotal' => $subtotal,
                'total' => $totals['total'],
                'cartCount' => $totals['count'],
                'removed' => !isset($cart[$id]),
            ]);
        }

        return $this->redirectToRoute('click_collect');
    }

    #[Route('/supprimer/{id}', name: 'remove', methods: ['POST'])]
    public function remove(int $id, Request $request, ProductRepository $productRepo): Response
    {
        $session = $request->getSession();
        $cart = $this->normalizeCart($session->get('cart', []), $productRepo);
        unset($cart[$id]);
        $session->set('cart', $cart);

        if ($request->isXmlHttpRequest()) {
            $totals = $this->computeCartTotals($cart);

            return new JsonResponse([
                'success' => true,
                'total' => $totals['total'],
                'cartCount' => $totals['count'],
            ]);
        }

        return $this->redirectToRoute('click_collect');
    }

    #[Route('/vider', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        $request->getSession()->set('cart', []);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('click_collect');
    }

    #[Route('/donnees', name: 'data', methods: ['GET'])]
    public function data(Request $request, ProductRepository $productRepo): JsonResponse
    {
        $cart = $this->normalizeCart($request->getSession()->get('cart', []), $productRepo);
        $request->getSession()->set('cart', $cart);
        $totals = $this->computeCartTotals($cart);

        return new JsonResponse([
            'items' => array_values($cart),
            'count' => $totals['count'],
            'total' => $totals['total'],
        ]);
    }

    #[Route('/commander', name: 'checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher,
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '[]', true);
        if (!is_array($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'JSON invalide.'], 400);
        }

        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $slot = trim((string) ($payload['slot'] ?? ''));
        $normalizedSlot = preg_replace('/\s*[–-]\s*/u', ' - ', $slot);

        if ($firstName === '' || $lastName === '' || $email === '' || $normalizedSlot === '') {
            return new JsonResponse(['success' => false, 'message' => 'Champs obligatoires manquants.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        if ($phone !== '' && strlen(preg_replace('/\D+/', '', $phone) ?? '') < 10) {
            return new JsonResponse(['success' => false, 'message' => 'Telephone invalide.'], 422);
        }

        if (!$this->isValidSlot($normalizedSlot)) {
            return new JsonResponse(['success' => false, 'message' => 'Creneau invalide ou indisponible.'], 422);
        }

        $cart = $this->normalizeCart($request->getSession()->get('cart', []), $productRepo);
        if ($cart === []) {
            return new JsonResponse(['success' => false, 'message' => 'Panier vide.'], 422);
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(16))));
            $user->setNeedsPasswordSetup(true);
            $em->persist($user);
        } else {
            if (!$user->getFirstName()) {
                $user->setFirstName($firstName);
            }
            if (!$user->getLastName()) {
                $user->setLastName($lastName);
            }
        }

        $order = new Orders();
        $order->setFirstName($firstName);
        $order->setLastName($lastName);
        $order->setEmail($email);
        $order->setPhone($phone !== '' ? $phone : null);
        $order->setComment($notes !== '' ? $notes : null);
        $order->setStatus(Orders::STATUS_NEW);
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setSlot($normalizedSlot);

        foreach ($cart as $row) {
            $product = $productRepo->find((int) $row['id']);
            if (!$product || !$product->isActive()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => sprintf('Le produit "%s" n\'est plus disponible.', $row['name'] ?? 'selectionne'),
                ], 409);
            }

            $qty = min((int) $row['quantity'], $product->getStock());
            if ($qty <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => sprintf('Le produit "%s" est en rupture de stock.', $product->getName()),
                ], 409);
            }

            $item = new OrderItem();
            $item->setProduct($product);
            $item->setProductName($product->getName());
            $item->setQuantity($qty);
            $item->setUnitPrice($product->getPrice());

            $order->addItem($item);
        }

        if ($order->getItems()->count() === 0) {
            return new JsonResponse(['success' => false, 'message' => 'Panier invalide.'], 422);
        }

        $order->recalculateTotal();

        $em->persist($order);
        $em->flush();

        $request->getSession()->set('cart', []);

        return new JsonResponse([
            'success' => true,
            'orderNumber' => $order->getOrderNumber(),
            'total' => $order->getTotal(),
        ]);
    }

    private function normalizeCart(array $rawCart, ProductRepository $productRepo): array
    {
        $cart = [];

        foreach ($rawCart as $id => $row) {
            $productId = (int) ($row['id'] ?? $id);
            $qty = (int) ($row['quantity'] ?? (is_numeric($row) ? $row : 0));

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $product = $productRepo->find($productId);
            if (!$product || !$product->isActive() || $product->getStock() <= 0) {
                continue;
            }

            $cart[$productId] = $this->buildCartRow($product, min($qty, $product->getStock()));
        }

        return $cart;
    }

    private function buildCartRow(Product $product, int $quantity): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'price' => (float) $product->getPrice(),
            'image' => $product->getPhoto(),
            'quantity' => $quantity,
            'stock' => $product->getStock(),
        ];
    }

    private function computeCartTotals(array $cart): array
    {
        $count = 0;
        $total = 0.0;

        foreach ($cart as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            $count += $quantity;
            $total += $quantity * $price;
        }

        return ['count' => $count, 'total' => $total];
    }

    private function extractQuantity(Request $request): int
    {
        return max(1, (int) ($this->extractRawQuantity($request) ?? 1));
    }

    private function extractRawQuantity(Request $request): int|string|null
    {
        if ($request->request->has('quantity')) {
            return $request->request->get('quantity');
        }

        $payload = json_decode($request->getContent() ?: '[]', true);

        return is_array($payload) ? ($payload['quantity'] ?? null) : null;
    }

    private function cartError(Request $request, string $message, int $status): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => $message], $status);
        }

        $this->addFlash('error', $message);

        return $this->redirect($request->headers->get('referer', $this->generateUrl('catalogue')));
    }

    private function isValidSlot(string $slot): bool
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})\|(.+)$/', $slot, $matches)) {
            return false;
        }

        [$fullMatch, $dateIso, $timeRange] = $matches;
        unset($fullMatch);

        $normalizedTimeRange = preg_replace('/\s*[–-]\s*/u', ' - ', $timeRange);

        if (!in_array($normalizedTimeRange, self::SLOT_TIMES, true)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateIso);
        if (!$date || $date->format('Y-m-d') !== $dateIso) {
            return false;
        }

        if ((int) $date->format('N') === 7) {
            return false;
        }

        $today = new \DateTimeImmutable('today');

        return $date >= $today && $date <= $today->modify('+7 days');
    }

    private function generateOrderNumber(): string
    {
        $date = (new \DateTimeImmutable())->format('Ymd');
        $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        return "NC-$date-$rand";
    }
}
