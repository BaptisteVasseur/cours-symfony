<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CancellationReasonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'label'       => 'Motif d\'annulation',
            'constraints' => [
                new NotBlank(message: 'Veuillez indiquer un motif.'),
                new Length(min: 10, minMessage: 'Le motif doit contenir au moins {{ limit }} caractères.'),
            ],
            'attr' => [
                'rows'        => 4,
                'placeholder' => 'Expliquez la raison de l\'annulation…',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'cancellation_reason',
        ]);
    }
}
