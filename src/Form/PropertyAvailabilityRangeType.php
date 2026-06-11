<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class PropertyAvailabilityRangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Debut',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'La date de debut est obligatoire.'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'Action',
                'choices' => [
                    'Bloquer les dates' => 'blocked',
                    'Rendre les dates disponibles' => 'available',
                ],
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Sejour minimum (nuits)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new GreaterThanOrEqual(1, message: 'Le sejour minimum doit etre d\'au moins {{ compared_value }} nuit.'),
                ],
            ])
            ->add('priceOverride', NumberType::class, [
                'label' => 'Tarif par nuit (optionnel)',
                'required' => false,
                'scale' => 2,
                'empty_data' => '',
                'html5' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'property_availability_range',
        ]);
    }
}
