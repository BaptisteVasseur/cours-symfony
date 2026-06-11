<?php

namespace App\Form;

use App\Entity\Amenity;
use App\Entity\CancellationPolicy;
use App\Entity\Listing;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('pricePerNight')
            ->add('maxGuests')
            ->add('bedrooms')
            ->add('bathrooms')
            ->add('propertyType')
            ->add('latitude')
            ->add('longitude')
            ->add('city')
            ->add('country')
            ->add('isActive')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('host', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            ->add('cancellationPolicy', EntityType::class, [
                'class' => CancellationPolicy::class,
                'choice_label' => 'id',
            ])
            ->add('amenities', EntityType::class, [
                'class' => Amenity::class,
                'choice_label' => 'id',
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Listing::class,
        ]);
    }
}
