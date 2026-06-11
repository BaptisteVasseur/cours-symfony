<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

class ReservationRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkinDate', DateType::class, [
                'label' => 'Arrivée',
                'widget' => 'single_text',
                'constraints' => [
                    new NotNull(message: 'La date d’arrivée est obligatoire.'),
                ],
            ])
            ->add('checkoutDate', DateType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
                'constraints' => [
                    new NotNull(message: 'La date de départ est obligatoire.'),
                ],
            ])
            ->add('guestsCount', IntegerType::class, [
                'label' => 'Voyageurs',
                'attr' => [
                    'min' => 1,
                ],
                'constraints' => [
                    new NotNull(message: 'Le nombre de voyageurs est obligatoire.'),
                    new GreaterThanOrEqual(value: 1, message: 'Il doit y avoir au moins un voyageur.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
