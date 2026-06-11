<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class HostICalSyncType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerName', TextType::class, [
                'label' => 'Nom du calendrier',
                'attr' => [
                    'placeholder' => 'Airbnb, Booking, Google Calendar...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du calendrier est obligatoire.'),
                    new Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas depasser {{ limit }} caracteres.'),
                ],
            ])
            ->add('iCalUrl', UrlType::class, [
                'label' => 'URL iCal',
                'attr' => [
                    'placeholder' => 'https://...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L URL iCal est obligatoire.'),
                    new Assert\Url(message: 'L URL iCal doit etre valide.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'host_ical_sync',
        ]);
    }
}
