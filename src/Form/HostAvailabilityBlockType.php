<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

class HostAvailabilityBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'constraints' => [
                    new NotNull(message: 'La date de début est obligatoire.'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'constraints' => [
                    new NotNull(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('priceOverride', NumberType::class, [
                'label' => 'Tarif nuit',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThanOrEqual(value: 0, message: 'Le tarif ne peut pas être négatif.'),
                ],
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Séjour min.',
                'required' => false,
                'attr' => [
                    'min' => 1,
                ],
                'constraints' => [
                    new GreaterThanOrEqual(value: 1, message: 'Le séjour minimum doit être supérieur à zéro.'),
                ],
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
