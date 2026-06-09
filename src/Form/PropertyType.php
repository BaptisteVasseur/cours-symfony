<?php

namespace App\Form;

use App\Entity\Property;
use App\Enum\PropertyStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400', 'rows' => 5],
            ])
            ->add('address', TextareaType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400', 'rows' => 2],
            ])
            ->add('city', TextType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('country', TextType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('pricePerNight', NumberType::class, [
                'constraints' => [new NotBlank(), new Positive()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('maxGuests', IntegerType::class, [
                'constraints' => [new NotBlank(), new Positive()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ])
            ->add('status', EnumType::class, [
                'class' => PropertyStatus::class,
                'choice_label' => fn(PropertyStatus $s) => ucfirst(strtolower($s->value)),
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Property::class]);
    }
}
