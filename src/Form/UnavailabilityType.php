<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Unavailability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UnavailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de début',
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de fin',
                'help' => 'La dernière nuit bloquée est la veille de cette date.',
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'label' => 'Motif (optionnel)',
                'attr' => ['rows' => 2, 'placeholder' => 'Travaux, séjour personnel, etc.'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Unavailability::class,
            'csrf_token_id' => 'unavailability_block',
        ]);
    }
}
