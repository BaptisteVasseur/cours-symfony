<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class UnavailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startAt', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'La date de début est obligatoire.'),
                    new GreaterThanOrEqual('now', message: 'Le début ne peut pas être dans le passé.'),
                ],
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('reason', ChoiceType::class, [
                'label' => 'Motif',
                'choices' => [
                    'Travaux' => 'Travaux',
                    'Usage personnel' => 'Usage personnel',
                    'Maintenance' => 'Maintenance',
                    'Autre' => 'Autre',
                ],
                'constraints' => [new NotBlank(message: 'Le motif est obligatoire.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'unavailability',
        ]);
    }
}
