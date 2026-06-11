<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Property;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PropertyAvailabilitySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minNights', IntegerType::class, [
                'label'    => 'Durée minimum de séjour (nuits)',
                'required' => false,
                'attr'     => [
                    'min'         => 1,
                    'placeholder' => 'Ex: 2',
                    'class'       => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand focus:border-brand',
                ],
                'constraints' => [
                    new Assert\GreaterThanOrEqual(value: 1, message: 'Minimum 1 nuit.'),
                ],
            ])
            ->add('pricePerNight', MoneyType::class, [
                'label'       => 'Tarif de base par nuit (€)',
                'currency'    => 'EUR',
                'required'    => true,
                'attr'        => [
                    'placeholder' => 'Ex: 80.00',
                    'class'       => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand focus:border-brand',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
