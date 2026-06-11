<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PropertyAvailabilityBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin (exclusive)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Le dernier jour bloqué est la veille de cette date.',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Travaux, usage personnel...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'property_availability_block',
        ]);
    }
}
