<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class BlockedPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateStart', DateType::class, [
                'label'       => 'Date de début',
                'widget'      => 'single_text',
                'constraints' => [new NotBlank()],
                'attr'        => ['min' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('dateEnd', DateType::class, [
                'label'       => 'Date de fin',
                'widget'      => 'single_text',
                'constraints' => [new NotBlank()],
                'attr'        => ['min' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('reason', TextType::class, [
                'label'    => 'Motif (optionnel)',
                'required' => false,
                'attr'     => ['placeholder' => 'Travaux, usage personnel…'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
