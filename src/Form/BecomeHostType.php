<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BecomeHostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Informations générales ──────────────────────────────
            ->add('title', TextType::class, [
                'label'       => 'Titre de l\'annonce',
                'attr'        => ['placeholder' => 'Ex : Appartement cosy au cœur de Paris'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 5, max: 255)],
            ])
            ->add('propertyType', ChoiceType::class, [
                'label'   => 'Type de logement',
                'choices' => [
                    'Appartement' => 'apartment',
                    'Maison'      => 'house',
                    'Villa'       => 'villa',
                    'Loft'        => 'loft',
                    'Chalet'      => 'chalet',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Décrivez votre logement…', 'rows' => 4],
            ])

            // ── Capacité ────────────────────────────────────────────
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Voyageurs maximum',
                'attr'  => ['min' => 1, 'placeholder' => '4'],
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
                'attr'  => ['min' => 0, 'placeholder' => '2'],
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
                'attr'  => ['min' => 1, 'placeholder' => '2'],
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
                'attr'  => ['min' => 1, 'placeholder' => '1'],
            ])

            // ── Prix ────────────────────────────────────────────────
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix par nuit (€)',
                'scale' => 2,
                'attr'  => ['min' => 1, 'placeholder' => '89'],
            ])
            ->add('cleaningFee', NumberType::class, [
                'label'    => 'Frais de ménage (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['min' => 0, 'placeholder' => '0'],
            ])
            ->add('securityDeposit', NumberType::class, [
                'label'    => 'Caution (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['min' => 0, 'placeholder' => '0'],
            ])

            // ── Horaires ────────────────────────────────────────────
            ->add('checkinTime', TimeType::class, [
                'label'    => 'Heure d\'arrivée',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['placeholder' => '15:00'],
            ])
            ->add('checkoutTime', TimeType::class, [
                'label'    => 'Heure de départ',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['placeholder' => '11:00'],
            ])

            // ── Options ─────────────────────────────────────────────
            ->add('instantBooking', CheckboxType::class, [
                'label'    => 'Réservation instantanée (sans validation hôte)',
                'required' => false,
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'label'        => 'Politique d\'annulation',
                'class'        => CancellationPolicy::class,
                'choice_label' => 'label',
                'placeholder'  => 'Choisissez une politique…',
            ])

            // ── Adresse (mapped: false → traité dans le contrôleur) ─
            ->add('city', TextType::class, [
                'label'    => 'Ville',
                'mapped'   => false,
                'attr'     => ['placeholder' => 'Ex : Paris'],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('country', TextType::class, [
                'label'    => 'Pays',
                'mapped'   => false,
                'data'     => 'France',
                'attr'     => ['placeholder' => 'France'],
            ])
            ->add('addressLine1', TextType::class, [
                'label'       => 'Adresse',
                'mapped'      => false,
                'constraints' => [new Assert\NotBlank()],
                'attr'        => ['placeholder' => '12 rue de la Paix'],
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
