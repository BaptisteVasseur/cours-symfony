<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkinDate', DateType::class, [
                'label' => 'Arrivée',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date d\'arrivée est obligatoire.')],
            ])
            ->add('checkoutDate', DateType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(message: 'La date de départ est obligatoire.'),
                ],
            ])
            ->add('guestsCount', IntegerType::class, [
                'label' => 'Voyageurs',
                'constraints' => [
                    new NotBlank(message: 'Le nombre de voyageurs est obligatoire.'),
                    new GreaterThanOrEqual(1, message: 'Il doit y avoir au moins {{ compared_value }} voyageur.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'booking',
        ]);
    }
}
