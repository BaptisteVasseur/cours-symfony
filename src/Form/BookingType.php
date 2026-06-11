<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
            // HiddenType — valeur texte brute fixée par Flatpickr via JS,
            // parsée manuellement dans le controller (évite les soucis du
            // DateTimeType caché avec les navigateurs).
            ->add('checkinDate', HiddenType::class, ['mapped' => false, 'required' => false])
            ->add('checkoutDate', HiddenType::class, ['mapped' => false, 'required' => false])
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
