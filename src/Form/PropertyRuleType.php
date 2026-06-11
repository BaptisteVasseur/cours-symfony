<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('petsAllowed', CheckboxType::class, [
                'label' => 'Animaux autorisés',
                'required' => false,
            ])
            ->add('smokingAllowed', CheckboxType::class, [
                'label' => 'Fumeurs autorisés',
                'required' => false,
            ])
            ->add('partiesAllowed', CheckboxType::class, [
                'label' => 'Fêtes autorisées',
                'required' => false,
            ])
            ->add('additionalRules', TextareaType::class, [
                'label' => 'Règles supplémentaires',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyRule::class,
        ]);
    }
}
