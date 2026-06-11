<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyUnavailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyUnavailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'La date de début de l\'indisponibilité',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'La date de fin de l\'indisponibilité',
            ])
            ->add('reason', ChoiceType::class, [
                'label' => 'Motif',
                'choices' => [
                    'Travaux' => 'maintenance',
                    'Utilisation personnelle' => 'personal_use',
                    'Nettoyage' => 'cleaning',
                    'Séjour du propriétaire' => 'owner_stay',
                    'Autre' => 'other',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                ],
                'help' => 'Ajoutez des détails sur cette période d\'indisponibilité',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyUnavailability::class,
        ]);
    }
}
