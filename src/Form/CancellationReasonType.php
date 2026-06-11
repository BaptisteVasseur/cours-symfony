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
            'label' => $options['label_text'],
            'constraints' => [
                new NotBlank(message: 'Le motif est obligatoire.'),
                new Length(max: 2000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.'),
            ],
            'attr' => [
                'rows' => 4,
                'placeholder' => 'Expliquez la raison...',
                'class' => 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_text' => 'Motif',
            'csrf_token_id' => 'cancellation',
        ]);

        $resolver->setAllowedTypes('label_text', 'string');
    }
}
