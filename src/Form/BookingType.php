<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkin', DateType::class, [
                'label'       => 'Arrivée',
                'widget'      => 'single_text',
                'constraints' => [new NotNull(message: 'La date d\'arrivée est requise.')],
                'attr'        => ['min' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('checkout', DateType::class, [
                'label'       => 'Départ',
                'widget'      => 'single_text',
                'constraints' => [new NotNull(message: 'La date de départ est requise.')],
            ])
            ->add('guestsCount', IntegerType::class, [
                'label'       => 'Voyageurs',
                'constraints' => [new Positive(message: 'Le nombre de voyageurs doit être positif.')],
                'attr'        => ['min' => 1],
                'data'        => 1,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
