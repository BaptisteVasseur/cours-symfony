<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requirePassword = $options['require_password'];

        $passwordConstraints = [
            new Length(min: 6, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
        ];
        if ($requirePassword) {
            array_unshift($passwordConstraints, new NotBlank(message: 'Veuillez saisir un mot de passe.'));
        }

        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('phone', TextType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Voyageur (guest)' => 'guest',
                    'Hôte (host)' => 'host',
                    'Administrateur (admin)' => 'admin',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Actif' => 'active',
                    'Suspendu' => 'suspended',
                    'Banni' => 'banned',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $requirePassword ? 'Mot de passe' : 'Nouveau mot de passe (laisser vide pour conserver)',
                'mapped' => false,
                'required' => $requirePassword,
                'constraints' => $passwordConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
