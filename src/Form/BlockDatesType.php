<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BlockDatesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label'       => 'Date de début',
                'widget'      => 'single_text',
                'constraints' => [
                    new Assert\NotNull(message: 'La date de début est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de début ne peut pas être dans le passé.',
                    ),
                ],
                'mapped' => false,
            ])
            ->add('endDate', DateType::class, [
                'label'       => 'Date de fin (incluse)',
                'widget'      => 'single_text',
                'constraints' => [new Assert\NotNull(message: 'La date de fin est obligatoire.')],
                'mapped'      => false,
            ])
            ->add('blockReason', TextType::class, [
                'label'       => 'Motif (optionnel)',
                'required'    => false,
                'mapped'      => false,
                'constraints' => [new Assert\Length(max: 255)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
