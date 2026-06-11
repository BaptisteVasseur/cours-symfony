<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PropertyAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('addressLine1', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [new NotBlank(message: 'L\'adresse est obligatoire.')],
            ])
            ->add('addressLine2', TextType::class, [
                'label' => 'Complément d\'adresse',
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new NotBlank(message: 'La ville est obligatoire.')],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'constraints' => [new NotBlank(message: 'Le pays est obligatoire.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyAddress::class,
        ]);
    }
}
