<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PropertyAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('addressLine1', TextType::class, ['label' => 'Adresse'])
            ->add('addressLine2', TextType::class, ['label' => 'Complément', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'Code postal'])
            ->add('city', TextType::class, ['label' => 'Ville'])
            ->add('country', TextType::class, ['label' => 'Pays'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyAddress::class,
        ]);
    }
}
