<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyAvailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Au (inclus)',
                'widget' => 'single_text',
            ])
            ->add('blockNote', TextareaType::class, [
                'label' => 'Motif (facultatif)',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Travaux, usage personnel…',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyAvailability::class,
            'csrf_token_id' => 'property_block',
        ]);
    }
}
