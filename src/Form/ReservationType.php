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
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'label' => 'Logement',
                'class' => Property::class,
                'choice_label' => 'title',
            ])
            ->add('guest', EntityType::class, [
                'label' => 'Voyageur',
                'class' => User::class,
                'choice_label' => 'email',
            ])
            ->add('checkinDate', DateType::class, ['label' => 'Arrivée', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('checkoutDate', DateType::class, ['label' => 'Départ', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('guestsCount', IntegerType::class, ['label' => 'Nombre de voyageurs'])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Confirmée' => 'confirmed',
                    'Annulée' => 'cancelled',
                    'Terminée' => 'completed',
                ],
            ])
            ->add('totalPrice', MoneyType::class, ['label' => 'Prix total', 'currency' => 'EUR'])
            ->add('cleaningFee', MoneyType::class, ['label' => 'Frais de ménage', 'currency' => 'EUR', 'required' => false])
            ->add('serviceFee', MoneyType::class, ['label' => 'Frais de service', 'currency' => 'EUR', 'required' => false])
            ->add('securityDeposit', MoneyType::class, ['label' => 'Caution', 'currency' => 'EUR', 'required' => false])
            ->add('currency', TextType::class, ['label' => 'Devise (3 lettres)']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Reservation::class]);
    }
}
