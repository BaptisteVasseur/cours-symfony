<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $userRepository,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {
                $errors[] = 'Token de sécurité invalide, veuillez réessayer.';
            } else {
                $firstName = trim($request->request->getString('firstName'));
                $lastName  = trim($request->request->getString('lastName'));
                $email     = trim($request->request->getString('email'));
                $password  = $request->request->getString('password');

                if ($firstName === '') $errors[] = 'Le prénom est obligatoire.';
                if ($lastName  === '') $errors[] = 'Le nom est obligatoire.';
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
                if (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';

                if (empty($errors) && $userRepository->findOneBy(['email' => $email])) {
                    $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
                }

                if (empty($errors)) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setPasswordHash($hasher->hashPassword($user, $password));
                    $user->setStatus('active');

                    $profile = new UserProfile();
                    $profile->setFirstName($firstName);
                    $profile->setLastName($lastName);
                    $user->setProfile($profile);

                    $em->persist($user);
                    $em->persist($profile);
                    $em->flush();

                    $this->addFlash('success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('home/register.html.twig', ['errors' => $errors]);
    }
}
