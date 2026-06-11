<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\BookingRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BookingRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkinDate', DateType::class, [
                'label' => 'Arrivée',
                'widget' => 'single_text',
            ])
            ->add('checkoutDate', DateType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
            ])
            ->add('guestsCount', IntegerType::class, [
                'label' => 'Voyageurs',
                'attr' => [
                    'min' => 1,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BookingRequest::class,
        ]);
    }
}
