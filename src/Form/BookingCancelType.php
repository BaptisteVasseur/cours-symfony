<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BookingCancelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('cancellationReason', TextareaType::class, [
            'label'       => 'Motif d\'annulation',
            'attr'        => [
                'rows'        => 3,
                'maxlength'   => 2000,
                'placeholder' => 'Expliquez la raison de l\'annulation…',
            ],
            'required'    => false,
            'constraints' => [
                new Assert\Length(max: 2000),
            ],
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
