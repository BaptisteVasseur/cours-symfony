<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Blockout;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BlockoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label'  => 'Date de début',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'attr'   => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand focus:border-brand'],
            ])
            ->add('endDate', DateType::class, [
                'label'  => 'Date de fin',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'attr'   => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand focus:border-brand'],
            ])
            ->add('reason', ChoiceType::class, [
                'label'       => 'Motif',
                'placeholder' => 'Choisir un motif',
                'required'    => false,
                'choices'     => [
                    'Travaux'          => 'travaux',
                    'Usage personnel'  => 'usage_personnel',
                    'Autre'            => 'autre',
                ],
                'attr' => ['class' => 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand focus:border-brand'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Blockout::class,
        ]);
    }
}
