<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class HostPropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Informations générales ──────────────────────────────
            ->add('title', TextType::class, [
                'label'       => 'Titre',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 5, max: 255)],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 4],
            ])
            ->add('propertyType', ChoiceType::class, [
                'label'   => 'Type de logement',
                'choices' => [
                    'Villa'       => 'villa',
                    'Loft'        => 'loft',
                    'Appartement' => 'apartment',
                    'Maison'      => 'house',
                    'Chalet'      => 'chalet',
                ],
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'label'        => 'Politique d\'annulation',
                'class'        => CancellationPolicy::class,
                'choice_label' => 'label',
                'placeholder'  => 'Choisissez une politique…',
            ])

            // ── Capacité ────────────────────────────────────────────
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Voyageurs max.',
                'attr'  => ['min' => 1],
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
                'attr'  => ['min' => 0],
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
                'attr'  => ['min' => 1],
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
                'attr'  => ['min' => 1],
            ])

            // ── Tarifs ──────────────────────────────────────────────
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix / nuit (€)',
                'scale' => 2,
                'attr'  => ['min' => 1],
            ])
            ->add('cleaningFee', NumberType::class, [
                'label'    => 'Frais de ménage (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['min' => 0],
            ])
            ->add('securityDeposit', NumberType::class, [
                'label'    => 'Caution (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['min' => 0],
            ])

            // ── Horaires ────────────────────────────────────────────
            ->add('checkinTime', TimeType::class, [
                'label'    => 'Heure d\'arrivée',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('checkoutTime', TimeType::class, [
                'label'    => 'Heure de départ',
                'widget'   => 'single_text',
                'required' => false,
            ])

            // ── Options ─────────────────────────────────────────────
            ->add('instantBooking', CheckboxType::class, [
                'label'    => 'Réservation instantanée',
                'required' => false,
            ])

            // ── Photos (mapped: false → traitées dans le contrôleur) ─
            ->add('imageUrls', CollectionType::class, [
                'mapped'         => false,
                'entry_type'     => UrlType::class,
                'entry_options'  => [
                    'label'       => false,
                    'required'    => false,
                    'attr'        => ['placeholder' => 'https://…'],
                    'constraints' => [new Assert\Url(requireTld: false)],
                ],
                'allow_add'      => true,
                'allow_delete'   => true,
                'required'       => false,
                'prototype'      => true,
                'prototype_name' => '__photo__',
            ])

            // ── Adresse (mapped: false → traité dans le contrôleur) ─
            ->add('addressLine1', TextType::class, [
                'label'       => 'Adresse',
                'mapped'      => false,
                'constraints' => [new Assert\NotBlank()],
                'attr'        => ['placeholder' => '12 rue de la Paix'],
            ])
            ->add('city', TextType::class, [
                'label'       => 'Ville',
                'mapped'      => false,
                'constraints' => [new Assert\NotBlank()],
                'attr'        => ['placeholder' => 'Paris'],
            ])
            ->add('country', TextType::class, [
                'label'   => 'Pays',
                'mapped'  => false,
                'data'    => 'France',
                'attr'    => ['placeholder' => 'France'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
