<?php

namespace App\Form\Admin;

use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Positive;

class OrderAddItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir un produit…',
            ])
            ->add('quantity', IntegerType::class, [
                'data' => 1,
                'constraints' => [
                    new Positive(),
                    new GreaterThanOrEqual(1),
                ],
            ])
        ;
    }
}