<?php

namespace App\Form;

use App\Entity\Amenity;
use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Title',
                'attr' => [
                    'placeholder' => 'Enter the title of the property',
                ],
            ])
            ->add('description')
            ->add('propertyType')
            ->add('address')
            ->add('city')
            ->add('country')
            ->add('postalCode')
            ->add('latitude')
            ->add('longitude')
            ->add('maxGuests')
            ->add('bedrooms')
            ->add('beds')
            ->add('bathrooms')
            ->add('pricePerNight')
            ->add('cleaningFee')
            ->add('status')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('image')
            ->add('host', EntityType::class, [
                'class' => User::class,
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
            'data_class' => Property::class,
        ]);
    }
}
