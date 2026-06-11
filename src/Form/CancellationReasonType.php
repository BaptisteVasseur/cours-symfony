<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CancellationReasonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'label' => 'Motif d\'annulation',
            'attr' => [
                'rows' => 4,
                'placeholder' => 'Expliquez la raison de votre annulation...',
            ],
            'constraints' => [
                new NotBlank(message: 'Le motif d\'annulation est obligatoire.'),
                new Length(min: 10, minMessage: 'Le motif doit contenir au moins {{ limit }} caractères.'),
            ],
        ]);
    }
}