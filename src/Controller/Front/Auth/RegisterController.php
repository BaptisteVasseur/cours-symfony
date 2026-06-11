<?php

declare(strict_types=1);

namespace App\Controller\Front\Auth;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $plainPassword = $form->get('plainPassword')->getData();

            if ($userRepository->findOneBy(['email' => $data['email']]) !== null) {
                $form->get('email')->addError(new FormError('Cette adresse e-mail est déjà utilisée.'));

                return $this->render('front/auth/register.html.twig', [
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $user = new User();
            $user->setEmail($data['email']);
            $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
            $user->setStatus('active');
            $user->setIsEmailVerified(false);
            $user->setPreferredLanguage('fr');
            $user->setPreferredCurrency('EUR');

            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setFirstName($data['firstName']);
            $profile->setLastName($data['lastName']);
            $profile->setIdentityStatus('unverified');

            $entityManager->persist($user);
            $entityManager->persist($profile);
            $entityManager->flush();

            $this->addFlash('success', 'Compte créé avec succès. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('front/auth/register.html.twig', [
            'form' => $form,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }
}
