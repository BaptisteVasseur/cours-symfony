<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Property;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('instantBooking', CheckboxType::class, [
                'label' => 'Activer la réservation instantanée',
                'help' => 'Si activé, les réservations seront automatiquement confirmées. Si désactivé, elles nécessiteront votre approbation.',
                'required' => false,
            ])
            ->add('checkinTime', TimeType::class, [
                'label' => 'Heure d\'arrivée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'Heure à laquelle les voyageurs peuvent arriver',
            ])
            ->add('checkoutTime', TimeType::class, [
                'label' => 'Heure de départ',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'Heure à laquelle les voyageurs doivent partir',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
