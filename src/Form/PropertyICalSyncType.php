<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class PropertyICalSyncType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerName', TextType::class, [
                'label' => 'Nom du calendrier',
                'required' => false,
                'attr' => ['placeholder' => 'Google Calendar, Airbnb...'],
            ])
            ->add('iCalUrl', UrlType::class, [
                'label' => 'URL du flux iCal (.ics)',
                'constraints' => [
                    new NotBlank(message: 'L\'URL iCal est obligatoire.'),
                    new Url(message: 'L\'URL n\'est pas valide.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\PropertyICalSync::class,
            'csrf_token_id' => 'property_ical_sync',
        ]);
    }
}
