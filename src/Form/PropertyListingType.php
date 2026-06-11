<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Property;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyListingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Nom de l\'annonce',
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix par nuit (€)',
                'scale' => 2,
            ])
            ->add('checkoutTime', TimeType::class, [
                'label' => 'Heure de départ maximale (checkout)',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('checkinTime', TimeType::class, [
                'label' => 'Heure d\'arrivée minimale (checkin)',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('instantBooking', CheckboxType::class, [
                'label' => 'Réservation instantanée',
                'help' => 'Les réservations sont confirmées automatiquement, sans validation de votre part.',
                'required' => false,
            ])
            ->add('allowSameDayBooking', CheckboxType::class, [
                'label' => 'Autoriser une arrivée le jour d\'un départ',
                'help' => 'Nécessite au moins 3 heures d\'écart entre l\'heure de départ et l\'heure d\'arrivée.',
                'required' => false,
            ])
            ->add('minimumStay', IntegerType::class, [
                'label' => 'Durée de séjour minimum (nuits)',
                'help' => 'Optionnel. Laissez vide pour aucune durée minimum.',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
            'csrf_token_id' => 'property_listing',
        ]);
    }
}
