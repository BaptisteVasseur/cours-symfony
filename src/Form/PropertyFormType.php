<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CancellationPolicy;
use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PropertyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
            ])
            ->add('pricePerNight', NumberType::class, [
                'label' => 'Prix par nuit',
                'scale' => 2,
            ])
            ->add('maxGuests', IntegerType::class, [
                'label' => 'Voyageurs maximum',
            ])
            ->add('bedrooms', IntegerType::class, [
                'label' => 'Chambres',
            ])
            ->add('beds', IntegerType::class, [
                'label' => 'Lits',
            ])
            ->add('bathrooms', IntegerType::class, [
                'label' => 'Salles de bain',
            ]);

        if ($options['show_owner']) {
            $builder->add('owner', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Hôte',
                'property_path' => 'host',
            ]);
        }

        $builder->add('cancellationPolicy', EntityType::class, [
                'class' => CancellationPolicy::class,
                'choice_label' => 'label',
                'label' => 'Politique d\'annulation',
            ])
            ->add('propertyType', ChoiceType::class, [
                'label' => 'Type de logement',
                'choices' => [
                    'Villa' => 'villa',
                    'Loft' => 'loft',
                    'Appartement' => 'apartment',
                    'Maison' => 'house',
                    'Chalet' => 'chalet',
                ],
            ])
            ->add('imageFiles', FileType::class, [
                'label' => 'Images',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new Assert\All([
                        new Assert\Image(),
                    ]),
                ],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $property = $event->getData();
            $address = $property instanceof Property ? $property->getAddress() : null;

            $event->getForm()
                ->add('address', TextType::class, [
                    'label' => 'Adresse',
                    'mapped' => false,
                    'data' => $address?->getAddressLine1(),
                    'constraints' => [new Assert\NotBlank()],
                ])
                ->add('city', TextType::class, [
                    'label' => 'Ville',
                    'mapped' => false,
                    'data' => $address?->getCity(),
                    'constraints' => [new Assert\NotBlank()],
                ])
                ->add('country', TextType::class, [
                    'label' => 'Pays',
                    'mapped' => false,
                    'data' => $address?->getCountry(),
                    'constraints' => [new Assert\NotBlank()],
                ])
                ->add('isActive', CheckboxType::class, [
                    'label' => 'Annonce active',
                    'mapped' => false,
                    'required' => false,
                    'data' => $property instanceof Property && $property->isActive(),
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $property = $event->getData();
            if (!$property instanceof Property) {
                return;
            }

            $form = $event->getForm();
            $address = $property->getAddress() ?? new PropertyAddress();
            $address
                ->setAddressLine1((string) $form->get('address')->getData())
                ->setCity((string) $form->get('city')->getData())
                ->setCountry((string) $form->get('country')->getData());

            $property
                ->setAddress($address)
                ->setIsActive((bool) $form->get('isActive')->getData());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
            'show_owner' => true,
        ]);
    }
}
