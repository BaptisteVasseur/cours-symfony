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
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class HostPropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('addressLine1', TextType::class, [
                'label' => 'Adresse',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'L\'adresse est obligatoire.')],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'La ville est obligatoire.')],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'Le pays est obligatoire.')],
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'class' => CancellationPolicy::class,
                'choice_label' => 'label',
                'label' => 'Politique d\'annulation',
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
                'label' => 'Voyageurs max.',
                'constraints' => [new GreaterThanOrEqual(1)],
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
                'constraints' => [new GreaterThanOrEqual(0)],
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
                'constraints' => [new GreaterThanOrEqual(1)],
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
                'constraints' => [new GreaterThanOrEqual(1)],
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix / nuit',
                'scale' => 2,
            ])
            ->add('cleaningFee', NumberType::class, [
                'label' => 'Frais de menage',
                'required' => false,
                'scale' => 2,
            ])
            ->add('securityDeposit', NumberType::class, [
                'label' => 'Caution',
                'required' => false,
                'scale' => 2,
            ])
            ->add('checkinTime', TimeType::class, [
                'label' => 'Heure d\'arrivee',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('checkoutTime', TimeType::class, [
                'label' => 'Heure de depart',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('instantBooking', CheckboxType::class, [
                'label' => 'Reservation instantanee',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
            'csrf_token_id' => 'host_property',
        ]);
    }
}
