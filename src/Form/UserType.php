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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [new Length(min: 8, max: 4096, minMessage: 'Au moins {{ limit }} caractères.')];
        if ($options['require_password']) {
            $passwordConstraints[] = new NotBlank(message: 'Le mot de passe est obligatoire.');
        }

        $builder
            ->add('email', EmailType::class, ['label' => 'Adresse e-mail'])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'Mot de passe' : 'Nouveau mot de passe (laisser vide pour ne pas changer)',
                'mapped' => false,
                'required' => $options['require_password'],
                'constraints' => $passwordConstraints,
            ])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Actif' => 'active',
                    'Suspendu (banni)' => 'suspended',
                ],
            ])
            ->add('preferredLanguage', TextType::class, ['label' => 'Langue préférée', 'required' => false])
            ->add('preferredCurrency', TextType::class, ['label' => 'Devise préférée', 'required' => false])
            ->add('isEmailVerified', CheckboxType::class, ['label' => 'E-mail vérifié', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => true,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
