<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Voyageur' => 'ROLE_USER',
                    'Hôte' => 'ROLE_HOST',
                    'Administrateur' => 'ROLE_ADMIN',
                    'Super administrateur' => 'ROLE_SUPER_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => (bool) $options['is_creation'],
                'empty_data' => null,
                'constraints' => (bool) $options['is_creation']
                    ? [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 8),
                    ]
                    : [
                        new Assert\Length(min: 8),
                    ],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();

            $event->getForm()
                ->add('firstName', TextType::class, [
                    'label' => 'Prénom',
                    'mapped' => false,
                    'data' => $user instanceof User ? $user->getFirstName() : null,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 2, max: 100),
                    ],
                ])
                ->add('lastName', TextType::class, [
                    'label' => 'Nom',
                    'mapped' => false,
                    'data' => $user instanceof User ? $user->getLastName() : null,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 2, max: 100),
                    ],
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof User) {
                return;
            }

            $form = $event->getForm();
            $user
                ->setFirstName((string) $form->get('firstName')->getData())
                ->setLastName((string) $form->get('lastName')->getData());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_creation' => false,
        ]);

        $resolver->setAllowedTypes('is_creation', 'bool');
    }
}
