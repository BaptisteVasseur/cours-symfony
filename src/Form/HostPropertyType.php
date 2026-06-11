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

class HostPropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. Appartement cosy au centre-ville'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 5, 'placeholder' => 'Décrivez votre logement, le quartier, les points forts… (min. 10 caractères)'],
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'class' => CancellationPolicy::class,
                'choice_label' => 'label',
                'label' => 'Politique d\'annulation',
                'placeholder' => 'Choisir une politique',
            ])
            ->add('propertyType', ChoiceType::class, [
                'label' => 'Type de logement',
                'choices' => [
                    'Appartement' => 'apartment',
                    'Maison' => 'house',
                    'Villa' => 'villa',
                    'Loft' => 'loft',
                    'Chalet' => 'chalet',
                ],
            ])
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Voyageurs maximum',
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix par nuit (€)',
                'scale' => 2,
            ])
            ->add('cleaningFee', NumberType::class, [
                'label' => 'Frais de ménage (€)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('securityDeposit', NumberType::class, [
                'label' => 'Caution (€)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('checkinTime', TimeType::class, [
                'label' => 'Heure d\'arrivée',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('checkoutTime', TimeType::class, [
                'label' => 'Heure de départ',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('instantBooking', CheckboxType::class, [
                'label' => 'Réservation instantanée',
                'required' => false,
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
