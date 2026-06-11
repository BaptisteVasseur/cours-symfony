<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminRejectType extends AbstractType
{
    public const PRESET_REASONS = [
        'Non-conformité aux règles de la maison'  => 'rules',
        'Indisponibilité non prévue du logement'  => 'unavailable',
        'Problème avec le profil du voyageur'     => 'profile',
        'Litige en cours sur ce logement'         => 'dispute',
        'Erreur de tarif ou de contenu'           => 'pricing',
        'Autre (préciser ci-dessous)'             => 'other',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('presetReason', ChoiceType::class, [
                'label'       => 'Motif de refus',
                'choices'     => self::PRESET_REASONS,
                'expanded'    => false,
                'placeholder' => '— Sélectionner un motif —',
                'required'    => true,
                'mapped'      => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un motif.'),
                ],
            ])
            ->add('customReason', TextareaType::class, [
                'label'    => 'Précisions (optionnel)',
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'rows'        => 3,
                    'maxlength'   => 2000,
                    'placeholder' => 'Détails supplémentaires…',
                ],
                'constraints' => [
                    new Assert\Length(max: 2000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
