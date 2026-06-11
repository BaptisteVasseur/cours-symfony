<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateStart', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
            ])
            ->add('dateEnd', DateType::class, [
                'label' => 'Au (exclu)',
                'widget' => 'single_text',
            ])
            ->add('reason', TextType::class, [
                'label' => 'Motif',
                'attr' => ['placeholder' => 'Travaux, usage personnel…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyBlock::class,
            'csrf_token_id' => 'property_block',
        ]);
    }
}
