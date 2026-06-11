<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RefuseReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', TextareaType::class, [
                'label' => 'Motif du refus',
                'help' => 'Ce motif sera communiqué au voyageur.',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Expliquez pourquoi vous refusez cette demande…',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le motif de refus est obligatoire.'),
                    new Length(min: 10, max: 1000, minMessage: 'Le motif doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'reservation_refuse',
        ]);
    }
}
