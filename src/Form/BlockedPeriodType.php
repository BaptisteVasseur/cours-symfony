<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyAvailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlockedPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Début de la période',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin de la période',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('reason', TextType::class, [
                'label' => 'Motif (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : travaux, usage personnel…'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyAvailability::class,
        ]);
    }
}
