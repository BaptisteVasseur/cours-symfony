<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');

        $builder
            ->add('checkIn', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual($today),
                ],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('checkOut', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('guestsCount', IntegerType::class, [
                'constraints' => [new NotBlank(), new Positive()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Booking::class]);
    }
}
