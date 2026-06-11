<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatut;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->render('registration/register.html.twig');
        }

        if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expire. Reessayez.');

            return $this->redirectToRoute('app_register');
        }

        $email = strtolower(trim((string) $request->request->get('email', '')));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $nom = trim((string) $request->request->get('nom', ''));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $role = (string) $request->request->get('role', UserRole::VOYAGEUR->value);
        $password = (string) $request->request->get('password', '');
        $passwordConfirmation = (string) $request->request->get('password_confirmation', '');
        $dateNaissance = $this->creerDateNaissance((string) $request->request->get('date_naissance', ''));
        $consentementCgu = $request->request->has('consentement_cgu');

        if ($prenom === '' || $nom === '' || $email === '' || $password === '') {
            $this->addFlash('error', 'Tous les champs obligatoires doivent etre renseignes.');

            return $this->redirectToRoute('app_register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide.');

            return $this->redirectToRoute('app_register');
        }

        if ($users->findOneBy(['email' => $email]) !== null) {
            $this->addFlash('error', 'Un compte existe deja avec cette adresse email.');

            return $this->redirectToRoute('app_register');
        }

        if ($password !== $passwordConfirmation) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');

            return $this->redirectToRoute('app_register');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caracteres.');

            return $this->redirectToRoute('app_register');
        }

        if ($dateNaissance === null || $dateNaissance->diff(new \DateTimeImmutable())->y < 18) {
            $this->addFlash('error', 'Vous devez avoir au moins 18 ans pour reserver ou publier un logement.');

            return $this->redirectToRoute('app_register');
        }

        if (!$consentementCgu) {
            $this->addFlash('error', 'Vous devez accepter les conditions generales.');

            return $this->redirectToRoute('app_register');
        }

        $user = new User();
        $user->prenom = $prenom;
        $user->nom = $nom;
        $user->email = $email;
        $user->telephone = $telephone !== '' ? $telephone : null;
        $user->dateNaissance = $dateNaissance;
        $user->role = $role === UserRole::HOTE->value ? UserRole::HOTE : UserRole::VOYAGEUR;
        $user->statut = UserStatut::EN_ATTENTE_VERIFICATION;
        $user->consentementCgu = true;
        $user->motDePasseHash = $passwordHasher->hashPassword($user, $password);

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Compte cree. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    private function creerDateNaissance(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }
}
