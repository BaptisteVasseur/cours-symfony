<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\ProfileEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }

        $profile = $user->getProfile();
        if ($profile === null) {
            $profile = new UserProfile();
            $user->setProfile($profile);
        }

        $form = $this->createForm(ProfileEditType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/messages', name: 'app_profile_messages', methods: ['GET'])]
    public function messages(): Response
    {
        return $this->render('profile/placeholder.html.twig', [
            'title' => 'Mes messages',
            'description' => 'La messagerie sera bientôt disponible.',
        ]);
    }

    #[Route('/settings', name: 'app_profile_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('profile/placeholder.html.twig', [
            'title' => 'Paramètres du compte',
            'description' => 'Cette page de paramètres est en cours de mise en place.',
        ]);
    }

    #[Route('/help', name: 'app_profile_help', methods: ['GET'])]
    public function help(): Response
    {
        return $this->render('profile/placeholder.html.twig', [
            'title' => "Centre d'aide",
            'description' => "Le centre d'aide sera disponible prochainement.",
        ]);
    }
}
