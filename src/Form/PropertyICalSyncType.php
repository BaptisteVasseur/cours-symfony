<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PropertyICalSync;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class PropertyICalSyncType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerName', TextType::class, [
                'label' => 'Nom du calendrier',
                'constraints' => [
                    new NotBlank(message: 'Le nom du calendrier est obligatoire.'),
                    new Length(max: 100),
                ],
            ])
            ->add('iCalUrl', UrlType::class, [
                'label' => 'URL iCal externe',
                'constraints' => [
                    new NotBlank(message: 'L\'URL iCal est obligatoire.'),
                    new Url(requireTld: false, message: 'L\'URL iCal n\'est pas valide.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropertyICalSync::class,
            'csrf_token_id' => 'property_ical_sync',
        ]);
    }
}
