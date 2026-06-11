<?php

namespace App\Form;

use App\Entity\BlockedPeriod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotNull;

class BlockedPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label'  => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [new NotNull(message: 'La date de début est requise.')],
            ])
            ->add('endDate', DateType::class, [
                'label'  => 'Date de fin',
                'widget' => 'single_text',
            ])
            ->add('reason', TextType::class, [
                'label'    => 'Motif',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : travaux, usage personnel…'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BlockedPeriod::class]);
    }
}
