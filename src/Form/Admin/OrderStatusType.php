<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $options['choices']; // garanti car défini par configureOptions()

        $builder->add('status', ChoiceType::class, [
            // Symfony attend un tableau "label => value"
            'choices' => array_combine($choices, $choices),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [], // option custom du formulaire
        ]);

        $resolver->setAllowedTypes('choices', 'array');
    }
}