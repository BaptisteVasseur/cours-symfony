<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRole;
use App\Form\RegistrationFormType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
        RoleRepository $roleRepository,
    ): Response {
        // déjà connecté -> accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
            $user->setStatus('active');

            // nouveau compte = voyageur par défaut
            $guestRole = $roleRepository->findOneBy(['code' => 'ROLE_GUEST']);
            if ($guestRole !== null) {
                $userRole = (new UserRole())
                    ->setUser($user)
                    ->setRole($guestRole);
                $user->addUserRole($userRole);
                $em->persist($userRole);
            }

            $em->persist($user);
            $em->flush();

            // connexion automatique après inscription
            $security->login($user);

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
