<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de déclaration d'une période d'indisponibilité par l'hôte.
 * Non lié à une entité : les dates sont éclatées en disponibilités jour par jour côté contrôleur.
 */
class AvailabilityBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('reason', ChoiceType::class, [
                'label' => 'Motif',
                'choices' => [
                    'Travaux' => 'travaux',
                    'Usage personnel' => 'usage_personnel',
                    'Autre' => 'autre',
                ],
                'placeholder' => 'Sélectionnez un motif',
                'constraints' => [new NotBlank(message: 'Le motif est obligatoire.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'availability_block',
        ]);
    }
}
