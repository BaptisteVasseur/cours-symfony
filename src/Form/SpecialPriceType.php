<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SpecialPriceType extends AbstractType
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
            ->add('priceOverride', NumberType::class, [
                'label'       => 'Tarif journalier (€)',
                'required'    => true,
                'scale'       => 2,
                'mapped'      => false,
                'constraints' => [
                    new Assert\NotNull(message: 'Le tarif est obligatoire.'),
                    new Assert\Positive(message: 'Le tarif doit être positif.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
