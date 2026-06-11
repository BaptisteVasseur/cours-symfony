<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\AccountProfileType;
use App\Form\AccountSettingsType;
use App\Form\HostPropertyType;
use App\Repository\PropertyRepository;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getProfile() === null) {
            $user->setProfile(new UserProfile());
        }

        $form = $this->createForm(AccountProfileType::class, $user->getProfile());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('front/account/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/parametres', name: 'app_account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AccountSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Paramètres enregistrés.');

            return $this->redirectToRoute('app_account_settings');
        }

        return $this->render('front/account/settings.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/proprietes', name: 'app_account_properties', methods: ['GET'])]
    public function properties(PropertyRepository $propertyRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/account/properties.html.twig', [
            'properties' => $propertyRepository->findByHost($user),
        ]);
    }

    #[Route('/proprietes/nouvelle', name: 'app_account_property_new', methods: ['GET', 'POST'])]
    public function newProperty(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setAddress(new PropertyAddress());
        $property->setHost($user);
        $property->setStatus('pending');

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!in_array(Roles::HOST, $user->getRoles(), true)) {
                $user->addAssignedRole(Roles::HOST);
            }

            $entityManager->persist($property);
            $entityManager->flush();

            $this->addFlash('success', 'Votre annonce a été créée et sera publiée après validation.');

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('front/host/property_new.html.twig', [
            'form' => $form,
        ]);
    }
}
