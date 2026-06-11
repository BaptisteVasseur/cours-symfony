<?php

namespace App\Form;

use App\Entity\PropertyAvailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class BlockAvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');

        $builder
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none', 'min' => $today->format('Y-m-d')],
                'label' => 'Début',
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none'],
                'label' => 'Fin (exclu)',
            ])
            ->add('reason', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none', 'placeholder' => 'Travaux, usage personnel…'],
                'label' => 'Motif (optionnel)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PropertyAvailability::class]);
    }
}
