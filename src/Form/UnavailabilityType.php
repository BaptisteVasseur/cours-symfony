<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyAvailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UnavailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('availableDate', DateType::class, [
                'label'  => 'Date bloquée',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('priceOverride', NumberType::class, [
                'label'    => 'Tarif spécifique ce jour (€)',
                'required' => false,
                'scale'    => 2,
            ])
            ->add('minimumStay', IntegerType::class, [
                'label'    => 'Séjour minimum (nuits)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyAvailability::class,
        ]);
    }
}
