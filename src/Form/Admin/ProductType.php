<?php

namespace App\Form\Admin;

use App\Entity\Product;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options)
    {
        $b
            ->add('name', TextType::class)
            ->add('slug', TextType::class)
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—'
            ])
            ->add('price', MoneyType::class, [
                'currency' => 'EUR',
                'divisor' => 1,
            ])
            ->add('stock', IntegerType::class)
            ->add('active', CheckboxType::class, ['required' => false])
            ->add('isNew', CheckboxType::class, ['required' => false])
            ->add('origin', TextType::class, ['required' => false])
            ->add('shortDescription', TextareaType::class, ['required' => false])
            ->add('longDescription', TextareaType::class, ['required' => false])
            ->add('metaTitle', TextType::class, ['required' => false])
            ->add('metaDescription', TextareaType::class, ['required' => false])
        ;
    }
}