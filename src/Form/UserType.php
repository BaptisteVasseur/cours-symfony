<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['is_creation'],
                'label' => $options['is_creation'] ? 'Mot de passe' : 'Nouveau mot de passe',
                'help' => $options['is_creation'] ? null : 'Laisser vide pour conserver le mot de passe actuel',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $options['is_creation']
                    ? [
                        new NotBlank(message: 'Le mot de passe est obligatoire.'),
                        new Length(
                            min: 8,
                            max: 128,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                            maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                        ),
                    ]
                    : [
                        new Length(
                            min: 8,
                            max: 128,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                            maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                        ),
                    ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut du compte',
                'choices' => [
                    'Actif' => 'active',
                    'En attente' => 'pending',
                    'Suspendu' => 'suspended',
                ],
            ])
            ->add('isEmailVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
            ])
            ->add('is2faEnabled', CheckboxType::class, [
                'label' => 'Double authentification activée',
                'required' => false,
            ])
            ->add('preferredLanguage', ChoiceType::class, [
                'label' => 'Langue',
                'required' => false,
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                    'Español' => 'es',
                ],
            ])
            ->add('preferredCurrency', ChoiceType::class, [
                'label' => 'Devise',
                'required' => false,
                'choices' => [
                    'EUR (€)' => 'EUR',
                    'USD ($)' => 'USD',
                    'GBP (£)' => 'GBP',
                ],
            ])
            ->add('profile', UserProfileType::class, [
                'label' => false,
            ])
        ;

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof User) {
                return;
            }

            $plainPassword = $event->getForm()->get('plainPassword')->getData();
            if (is_string($plainPassword) && $plainPassword !== '') {
                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_creation' => false,
            'validation_groups' => function (FormInterface $form): array {
                return $form->getConfig()->getOption('is_creation')
                    ? ['Default', 'create']
                    : ['Default'];
            },
        ]);

        $resolver->setAllowedTypes('is_creation', 'bool');
    }
}
