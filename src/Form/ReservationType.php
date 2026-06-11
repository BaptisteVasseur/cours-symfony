<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'choice_label' => 'title',
                'label' => 'Logement',
            ])
            ->add('guest', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Voyageur',
            ])
            ->add('checkinDate', DateType::class, [
                'label' => 'Date d\'arrivée',
                'widget' => 'single_text',
            ])
            ->add('checkoutDate', DateType::class, [
                'label' => 'Date de départ',
                'widget' => 'single_text',
            ])
            ->add('guestsCount', IntegerType::class, [
                'label' => 'Nombre de voyageurs',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Confirmée' => 'confirmed',
                    'Terminée' => 'completed',
                    'Annulée' => 'cancelled',
                ],
            ])
            ->add('totalPrice', NumberType::class, [
                'label' => 'Prix total',
                'scale' => 2,
            ])
            ->add('cleaningFee', NumberType::class, [
                'label' => 'Frais de ménage',
                'required' => false,
                'scale' => 2,
            ])
            ->add('serviceFee', NumberType::class, [
                'label' => 'Frais de service',
                'required' => false,
                'scale' => 2,
            ])
            ->add('securityDeposit', NumberType::class, [
                'label' => 'Caution',
                'required' => false,
                'scale' => 2,
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => [
                    'EUR (€)' => 'EUR',
                    'USD ($)' => 'USD',
                    'GBP (£)' => 'GBP',
                ],
            ])
            ->add('cancellationReason', TextareaType::class, [
                'label' => 'Motif d\'annulation',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
