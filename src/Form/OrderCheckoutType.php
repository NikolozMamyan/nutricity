<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OrderCheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est obligatoire.'),
                    new Assert\Length(max: 100, maxMessage: 'Le prénom ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(max: 100, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email (pour recevoir confirmation)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'ex: nom@domaine.fr',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new Assert\Email(message: "Le format d'email est invalide."),
                    new Assert\Length(max: 180, maxMessage: "L'email ne doit pas dépasser {{ limit }} caractères."),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: 06 12 34 56 78',
                    'autocomplete' => 'tel',
                ],
                'constraints' => [
                    new Assert\Length(max: 30, maxMessage: 'Le téléphone ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Instructions / commentaire',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Ex: merci de remplacer un produit si indisponible…',
                ],
                'constraints' => [
                    new Assert\Length(max: 2000, maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Ici on suit la doc : le controller récupère un tableau $data = $form->getData()
        // (pas de data_class imposée)
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
