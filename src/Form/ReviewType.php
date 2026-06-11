<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label'   => 'Note',
                'choices' => [
                    '⭐ 1 — Très mauvais' => 1,
                    '⭐⭐ 2 — Mauvais'    => 2,
                    '⭐⭐⭐ 3 — Correct'   => 3,
                    '⭐⭐⭐⭐ 4 — Bien'     => 4,
                    '⭐⭐⭐⭐⭐ 5 — Excellent' => 5,
                ],
                'expanded' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label'    => 'Commentaire',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Décrivez votre expérience…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
