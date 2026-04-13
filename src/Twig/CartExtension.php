<?php

namespace App\Twig;

use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CartExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private CartService $cart) {}

    public function getGlobals(): array
    {
        return ['cart_count' => $this->cart->getCount()];
    }
}
