<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('propertyType', ChoiceType::class, [
                'label' => 'Type de logement',
                'choices' => [
                    'Appartement' => 'appartement',
                    'Maison' => 'maison',
                    'Chalet' => 'chalet',
                    'Loft' => 'loft',
                    'Studio' => 'studio',
                    'Villa' => 'villa',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'Publié' => 'published',
                    'Suspendu' => 'suspended',
                ],
            ])
            ->add('maxGuests', IntegerType::class, ['label' => 'Voyageurs max'])
            ->add('bedrooms', IntegerType::class, ['label' => 'Chambres'])
            ->add('beds', IntegerType::class, ['label' => 'Lits'])
            ->add('bathrooms', IntegerType::class, ['label' => 'Salles de bain'])
            ->add('pricePerNight', MoneyType::class, ['label' => 'Prix / nuit', 'currency' => 'EUR'])
            ->add('cleaningFee', MoneyType::class, ['label' => 'Frais de ménage', 'currency' => 'EUR', 'required' => false])
            ->add('securityDeposit', MoneyType::class, ['label' => 'Caution', 'currency' => 'EUR', 'required' => false])
            ->add('instantBooking', CheckboxType::class, ['label' => 'Réservation instantanée', 'required' => false])
            ->add('host', EntityType::class, [
                'label' => 'Hôte',
                'class' => User::class,
                'choice_label' => 'email',
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'label' => 'Politique d\'annulation',
                'class' => CancellationPolicy::class,
                'choice_label' => 'label',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Property::class]);
    }
}
