<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    public function __construct(
        private RequestStack $requestStack,
        private ProductRepository $productRepo,
    ) {
    }

    public function add(int $productId, int $qty = 1): void
    {
        $product = $this->productRepo->find($productId);
        if (!$product || !$product->isActive() || $product->getStock() <= 0) {
            return;
        }

        $cart = $this->getCart();
        $currentQty = (int) ($cart[$productId]['quantity'] ?? 0);
        $cart[$productId] = $this->buildRow($product, min($product->getStock(), $currentQty + max(1, $qty)));

        $this->saveCart($cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->getCart();
        unset($cart[$productId]);
        $this->saveCart($cart);
    }

    public function update(int $productId, int $qty): void
    {
        $cart = $this->getCart();
        $product = $this->productRepo->find($productId);

        if ($qty <= 0 || !$product || !$product->isActive() || $product->getStock() <= 0) {
            unset($cart[$productId]);
            $this->saveCart($cart);

            return;
        }

        $cart[$productId] = $this->buildRow($product, min($qty, $product->getStock()));
        $this->saveCart($cart);
    }

    public function getItems(): array
    {
        return array_values($this->getCart());
    }

    public function getTotal(): float
    {
        return array_reduce($this->getCart(), static function (float $total, array $item): float {
            return $total + ((float) $item['price'] * (int) $item['quantity']);
        }, 0.0);
    }

    public function getCount(): int
    {
        return array_reduce($this->getCart(), static function (int $count, array $item): int {
            return $count + (int) ($item['quantity'] ?? 0);
        }, 0);
    }

    public function clear(): void
    {
        $this->getSession()->remove('cart');
    }

    private function getCart(): array
    {
        $rawCart = $this->getSession()->get('cart', []);
        $cart = [];

        foreach ($rawCart as $id => $row) {
            $productId = (int) ($row['id'] ?? $id);
            $qty = (int) ($row['quantity'] ?? (is_numeric($row) ? $row : 0));

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $product = $this->productRepo->find($productId);
            if (!$product || !$product->isActive() || $product->getStock() <= 0) {
                continue;
            }

            $cart[$productId] = $this->buildRow($product, min($qty, $product->getStock()));
        }

        return $cart;
    }

    private function saveCart(array $cart): void
    {
        $this->getSession()->set('cart', $cart);
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    private function buildRow(Product $product, int $quantity): array
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
}
