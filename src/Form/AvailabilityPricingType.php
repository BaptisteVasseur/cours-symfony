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

/**
 * Formulaire optionnel (spécification A.1) : configuration d'un tarif journalier
 * spécifique et/ou d'une durée de séjour minimum sur une plage de dates.
 */
class AvailabilityPricingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('priceOverride', NumberType::class, [
                'label' => 'Tarif journalier (€)',
                'required' => false,
                'scale' => 2,
                'constraints' => [new Positive(message: 'Le tarif doit être supérieur à 0.')],
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Séjour minimum (nuits)',
                'required' => false,
                'constraints' => [new GreaterThanOrEqual(1, message: 'Le séjour minimum doit être d\'au moins {{ compared_value }} nuit.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'availability_pricing',
        ]);
    }
}
