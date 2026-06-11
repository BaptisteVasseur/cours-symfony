<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class BlockDatesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fromDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('toDate', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('reason', TextType::class, [
                'label' => 'Motif (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: maintenance, vacances en famille...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'block_dates',
        ]);
    }
}
