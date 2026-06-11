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

class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'annonce',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('propertyType', ChoiceType::class, [
                'label' => 'Type de logement',
                'choices' => [
                    'Villa' => 'villa',
                    'Loft' => 'loft',
                    'Appartement' => 'apartment',
                    'Maison' => 'house',
                    'Chalet' => 'chalet',
                ],
                'placeholder' => 'Sélectionner un type',
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'class' => CancellationPolicy::class,
                'choice_label' => 'label',
                'label' => 'Politique d\'annulation',
                'placeholder' => 'Sélectionner une politique',
            ])
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Voyageurs max.',
                'attr' => ['min' => 1],
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
                'attr' => ['min' => 0],
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
                'attr' => ['min' => 1],
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
                'attr' => ['min' => 1],
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix / nuit (€)',
                'scale' => 2,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('cleaningFee', NumberType::class, [
                'label' => 'Frais de ménage (€)',
                'required' => false,
                'scale' => 2,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('securityDeposit', NumberType::class, [
                'label' => 'Caution (€)',
                'required' => false,
                'scale' => 2,
                'attr' => ['min' => 0, 'step' => '0.01'],
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
            ->add('address', PropertyAddressType::class, [
                'label' => false,
            ])
            ->add('rules', PropertyRuleType::class, [
                'label' => false,
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
