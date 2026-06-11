<?php

namespace App\Form;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyRule;
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
            ->add('title')
            ->add('description')
            ->add('propertyType')
            ->add('status')
            ->add('maxGuests')
            ->add('bedrooms')
            ->add('beds')
            ->add('bathrooms')
            ->add('pricePerNight')
            ->add('cleaningFee')
            ->add('securityDeposit')
            ->add('checkinTime', null, [
                'widget' => 'single_text',
            ])
            ->add('checkoutTime', null, [
                'widget' => 'single_text',
            ])
            ->add('instantBooking')
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
            ->add('address', EntityType::class, [
                'class' => PropertyAddress::class,
                'choice_label' => 'id',
            ])
            ->add('rules', EntityType::class, [
                'class' => PropertyRule::class,
                'choice_label' => 'id',
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
