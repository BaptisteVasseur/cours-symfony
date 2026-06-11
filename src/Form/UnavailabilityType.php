<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyUnavailability;
use App\Enum\UnavailabilityReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UnavailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin (exclue)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('reason', EnumType::class, [
                'label' => 'Motif',
                'class' => UnavailabilityReason::class,
                'choice_label' => static fn (UnavailabilityReason $reason): string => $reason->label(),
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note (optionnel)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyUnavailability::class,
            'csrf_token_id' => 'unavailability',
        ]);
    }
}
