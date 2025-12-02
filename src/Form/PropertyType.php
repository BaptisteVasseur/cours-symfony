<?php

namespace App\Form;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
//            ->add('address')
//            ->add('city')
//            ->add('pricePerNight')
//            ->add('maxGuests')
//            ->add('bedrooms')
//            ->add('bathrooms')
//            ->add('isActive')
//            ->add('createdAt', null, [
//                'widget' => 'single_text',
//            ])
//            ->add('note')
//            ->add('host', EntityType::class, [
//                'class' => User::class,
//                'choice_label' => 'id',
//            ])
            ->add('submit', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
