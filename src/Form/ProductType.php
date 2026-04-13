<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

// VichUploaderBundle
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false, // auto-généré si vide via subscriber (doc)
                'help' => 'Laisser vide pour génération automatique.',
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('longDescription', TextareaType::class, [
                'label' => 'Description complète',
                'required' => false,
                'attr' => ['rows' => 8], // CKEditor optionnel côté projet
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '— Aucune —',
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prix est obligatoire.'),
                    new Assert\Positive(message: 'Le prix doit être positif.'),
                ],
            ])
            // ->add('imageFile', VichImageType::class, [
            //     'label' => 'Photo produit',
            //     'required' => false,
            //     'download_uri' => false,
            //     'allow_delete' => true,
            //     'help' => 'PNG/JPG/WebP. Laisser vide pour conserver.',
            // ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'required' => true,
                'attr' => ['min' => 0],
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0, message: 'Le stock ne peut pas être négatif.'),
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Actif (visible)',
                'required' => false,
            ])
            ->add('origin', TextType::class, [
                'label' => 'Origine',
                'required' => false,
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('isNew', CheckboxType::class, [
                'label' => 'Badge “Nouveau”',
                'required' => false,
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta title (SEO)',
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta description (SEO)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'maxlength' => 160,
                ],
                'help' => 'Idéalement ≤ 160 caractères.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
