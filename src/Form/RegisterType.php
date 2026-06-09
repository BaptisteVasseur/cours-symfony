<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-rose-400';

        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => $inputClass],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [new NotBlank()],
                'attr' => ['class' => $inputClass],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank(), new Email()],
                'attr' => ['class' => $inputClass],
            ])
            ->add('role', EnumType::class, [
                'class' => UserRole::class,
                'choices' => [UserRole::TRAVELER, UserRole::HOST],
                'choice_label' => fn(UserRole $r) => ucfirst(strtolower($r->value)),
                'attr' => ['class' => $inputClass],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'constraints' => [new NotBlank(), new Length(['min' => 8])],
                    'attr' => ['class' => $inputClass],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['class' => $inputClass],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
