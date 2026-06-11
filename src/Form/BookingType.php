<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkinDate', DateType::class, [
                'label'      => 'Date d\'arrivée',
                'widget'     => 'single_text',
                'attr'       => ['class' => 'hidden'],
                'label_attr' => ['class' => 'hidden'],
                'constraints' => [
                    new Assert\NotNull(message: 'La date d\'arrivée est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date d\'arrivée ne peut pas être dans le passé.',
                    ),
                ],
            ])
            ->add('checkoutDate', DateType::class, [
                'label'      => 'Date de départ',
                'widget'     => 'single_text',
                'attr'       => ['class' => 'hidden'],
                'label_attr' => ['class' => 'hidden'],
                'constraints' => [
                    new Assert\NotNull(message: 'La date de départ est obligatoire.'),
                ],
            ])
            ->add('guestsCount', IntegerType::class, [
                'label'      => 'Nombre de voyageurs',
                'attr'       => ['class' => 'hidden', 'min' => 1],
                'label_attr' => ['class' => 'hidden'],
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\GreaterThanOrEqual(1),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Reservation::class,
            'validation_groups'  => ['booking_checkout'],
        ]);
    }
}
