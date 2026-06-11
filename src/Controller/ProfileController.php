<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_profile_show', methods: ['GET', 'POST'])]
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profile = $user->getProfile();

        if ($profile === null) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $user->setProfile($profile);
        }

        // On crée le formulaire de profil. On désactive la modification du statut d'identité pour le user normal.
        $form = $this->createForm(UserProfileType::class, $profile);
        if ($form->has('identityStatus')) {
            $form->remove('identityStatus');
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profile->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('app_profile_show');
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'form' => $form,
        ]);
    }

    #[Route('/settings', name: 'app_profile_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Formulaire de paramètres simples (email, PreferredLanguage, PreferredCurrency)
        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
            ])
            ->add('preferredLanguage', ChoiceType::class, [
                'label' => 'Langue préférée',
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                    'Español' => 'es',
                ],
                'required' => false,
            ])
            ->add('preferredCurrency', ChoiceType::class, [
                'label' => 'Devise préférée',
                'choices' => [
                    'Euro (€)' => 'EUR',
                    'Dollar ($)' => 'USD',
                    'Livre Sterling (£)' => 'GBP',
                ],
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'Vos préférences ont été enregistrées.');

            return $this->redirectToRoute('app_profile_settings');
        }

        return $this->render('profile/settings.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
