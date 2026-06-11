<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\Listing;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('listing', EntityType::class, [
                'label' => 'Logement',
                'class' => Listing::class,
                'choice_label' => 'title',
            ])
            ->add('checkIn', DateType::class, [
                'label' => 'Arrivée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('checkOut', DateType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('guestsCount', IntegerType::class, ['label' => 'Nombre de voyageurs', 'required' => false])
            ->add('nightsCount', IntegerType::class, ['label' => 'Nombre de nuits', 'required' => false])
            ->add('baseAmount', MoneyType::class, ['label' => 'Montant de base', 'currency' => 'EUR'])
            ->add('cleaningFee', MoneyType::class, ['label' => 'Frais de ménage', 'currency' => 'EUR', 'required' => false])
            ->add('serviceFee', MoneyType::class, ['label' => 'Frais de service', 'currency' => 'EUR', 'required' => false])
            ->add('taxesAmount', MoneyType::class, ['label' => 'Taxes', 'currency' => 'EUR', 'required' => false])
            ->add('totalAmount', MoneyType::class, ['label' => 'Montant total', 'currency' => 'EUR'])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'required' => false,
                'choices' => ['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'],
            ])
            ->add('bookingStatus', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Confirmée' => 'confirmed',
                    'Terminée' => 'completed',
                    'Annulée' => 'cancelled',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
