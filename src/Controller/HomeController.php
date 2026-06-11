<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'properties' => $propertyRepository->findForListing(),
        ]);
    }

    #[Route('/logement/{id}', name: 'app_logement_detail')]
    public function detail(Property $property, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->findOneForDetail($property) ?? $property;

        return $this->render('home/logement.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, PropertyRepository $propertyRepository): Response
    {
        $checkin = $this->parseDate($request->query->get('checkin'));
        $checkout = $this->parseDate($request->query->get('checkout'));

        return $this->render('home/search.html.twig', [
            'properties' => $propertyRepository->findForListing(),
            'checkin' => $checkin,
            'checkout' => $checkout,
            'guests' => $request->query->getInt('guests'),
            'destination' => $request->query->get('destination'),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        $values = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
        ];

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $values['firstName'] = trim($payload->getString('firstName'));
            $values['lastName'] = trim($payload->getString('lastName'));
            $values['email'] = mb_strtolower(trim($payload->getString('email')));
            $password = $payload->getString('password');

            $errors = [];

            if (!$this->isCsrfTokenValid('register', $payload->getString('_token'))) {
                $errors[] = 'Jeton de sécurité invalide, veuillez réessayer.';
            }
            if ($values['firstName'] === '' || $values['lastName'] === '') {
                $errors[] = 'Le prénom et le nom sont obligatoires.';
            }
            if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'L\'adresse e-mail n\'est pas valide.';
            }
            if (mb_strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if ($values['email'] !== '' && $userRepository->findOneBy(['email' => $values['email']]) !== null) {
                $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
            }

            if ($errors === []) {
                $user = new User();
                $user->setEmail($values['email']);
                $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                $user->setStatus('active');
                $user->setIsEmailVerified(false);
                $user->setPreferredLanguage('fr');
                $user->setPreferredCurrency('EUR');
                $user->setAssignedRoles([]);

                $profile = new UserProfile();
                $profile->setUser($user);
                $profile->setFirstName($values['firstName']);
                $profile->setLastName($values['lastName']);
                $profile->setIdentityStatus('unverified');
                $user->setProfile($profile);

                $entityManager->persist($user);
                $entityManager->persist($profile);
                $entityManager->flush();

                $security->login($user);
                $this->addFlash('success', 'Bienvenue, votre compte a bien été créé.');

                return $this->redirectToRoute('app_home');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('home/register.html.twig', [
            'values' => $values,
        ]);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
