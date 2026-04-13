<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class CsvImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csvFile', FileType::class, [
                'label' => 'Fichier CSV',
                'required' => true,
                'mapped' => false, // upload pur (pas une entité)
                'constraints' => [
                    new NotNull(message: 'Veuillez sélectionner un fichier CSV.'),
                    new File([
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ],
                        'mimeTypesMessage' => 'Le fichier doit être un CSV valide.',
                        'maxSize' => '10M',
                    ]),
                ],
                'attr' => [
                    'accept' => '.csv,text/csv,text/plain',
                ],
            ])
            ->add('delimiter', ChoiceType::class, [
                'label' => 'Délimiteur',
                'required' => true,
                'choices' => [
                    ';' => ';',
                    ',' => ',',
                    '|' => '|',
                ],
                'data' => ';',
                'help' => 'Par défaut : ";"',
            ])
            ->add('updateExisting', CheckboxType::class, [
                'label' => 'Mettre à jour si le slug existe déjà',
                'required' => false,
                'help' => 'Si décoché, les doublons (slug existant) peuvent être ignorés.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
