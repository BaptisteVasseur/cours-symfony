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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class PropertyAvailabilityConfigureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin (exclusive)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('priceOverride', NumberType::class, [
                'label' => 'Prix par nuit (€)',
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'constraints' => [new Positive(message: 'Le prix doit être positif.')],
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Durée minimale (nuits)',
                'required' => false,
                'constraints' => [new GreaterThanOrEqual(1, message: 'La durée minimale doit être d\'au moins {{ compared_value }} nuit.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'property_availability_configure',
        ]);
    }
}
