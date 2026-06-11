<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CancellationReasonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'constraints' => [new NotBlank(message: 'Veuillez indiquer un motif d\'annulation.')],
            'attr' => [
                'class' => 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none',
                'rows' => 4,
                'placeholder' => 'Expliquez pourquoi vous souhaitez annuler cette réservation…',
            ],
            'label' => 'Motif d\'annulation',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
