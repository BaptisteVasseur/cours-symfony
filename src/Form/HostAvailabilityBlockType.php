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
                'label' => 'Debut',
                'widget' => 'single_text',
                'attr' => [
                    'data-calendar-start' => '1',
                ],
                'constraints' => [
                    new NotNull(message: 'La date de debut est obligatoire.'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'attr' => [
                    'data-calendar-end' => '1',
                ],
                'constraints' => [
                    new NotNull(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('priceOverride', NumberType::class, [
                'label' => 'Tarif nuit',
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new GreaterThanOrEqual(value: 0, message: 'Le tarif ne peut pas etre negatif.'),
                ],
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Sejour min.',
                'required' => false,
                'attr' => [
                    'min' => 1,
                ],
                'constraints' => [
                    new GreaterThanOrEqual(value: 1, message: 'Le sejour minimum doit etre superieur a zero.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
