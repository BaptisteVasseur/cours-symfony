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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'choice_label' => 'title',
                'label' => 'Annonce',
            ])
            ->add('guest', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Voyageur',
            ])
            ->add('guestsCount', IntegerType::class, [
                'label' => 'Voyageurs',
            ])
            ->add('totalPrice', NumberType::class, [
                'label' => 'Total',
                'scale' => 2,
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
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $booking = $event->getData();

            $event->getForm()
                ->add('startDate', DateType::class, [
                    'label' => 'Arrivée',
                    'mapped' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'data' => $booking instanceof Reservation ? $booking->getCheckinDate() : null,
                    'constraints' => [new Assert\NotNull()],
                ])
                ->add('endDate', DateType::class, [
                    'label' => 'Départ',
                    'mapped' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'data' => $booking instanceof Reservation ? $booking->getCheckoutDate() : null,
                    'constraints' => [new Assert\NotNull()],
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $booking = $event->getData();
            if (!$booking instanceof Reservation) {
                return;
            }

            $form = $event->getForm();
            $startDate = $form->get('startDate')->getData();
            $endDate = $form->get('endDate')->getData();

            if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable) {
                $booking
                    ->setCheckinDate($startDate)
                    ->setCheckoutDate($endDate);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
