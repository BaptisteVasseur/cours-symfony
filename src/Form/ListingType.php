<?php

namespace App\Form;

use App\Entity\Amenity;
use App\Entity\Listing;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('propertyType', ChoiceType::class, [
                'label' => 'Type de bien',
                'required' => false,
                'choices' => [
                    'Appartement' => 'apartment',
                    'Maison' => 'house',
                    'Studio' => 'studio',
                    'Villa' => 'villa',
                    'Chalet' => 'chalet',
                ],
            ])
            ->add('roomType', ChoiceType::class, [
                'label' => 'Type de location',
                'required' => false,
                'choices' => [
                    'Logement entier' => 'entire_home',
                    'Chambre privée' => 'private_room',
                    'Chambre partagée' => 'shared_room',
                ],
            ])
            ->add('maxGuests', IntegerType::class, ['label' => 'Voyageurs max', 'required' => false])
            ->add('bedrooms', IntegerType::class, ['label' => 'Chambres', 'required' => false])
            ->add('beds', IntegerType::class, ['label' => 'Lits', 'required' => false])
            ->add('bathrooms', IntegerType::class, ['label' => 'Salles de bain', 'required' => false])
            ->add('pricePerNight', MoneyType::class, ['label' => 'Prix par nuit', 'currency' => 'EUR'])
            ->add('cleaningFee', MoneyType::class, ['label' => 'Frais de ménage', 'currency' => 'EUR', 'required' => false])
            ->add('serviceFee', MoneyType::class, ['label' => 'Frais de service', 'currency' => 'EUR', 'required' => false])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'required' => false,
                'choices' => ['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Brouillon' => 'draft',
                    'Publié' => 'published',
                    'Archivé' => 'archived',
                ],
            ])
            ->add('instantBooking', CheckboxType::class, ['label' => 'Réservation instantanée', 'required' => false])
            ->add('cancellationPolicy', ChoiceType::class, [
                'label' => 'Politique d\'annulation',
                'required' => false,
                'choices' => [
                    'Flexible' => 'flexible',
                    'Modérée' => 'moderate',
                    'Stricte' => 'strict',
                ],
            ])
            ->add('amenities', EntityType::class, [
                'label' => 'Équipements',
                'class' => Amenity::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Listing::class,
        ]);
    }
}
