<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\CancellationPolicy;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PropertyRoleTest extends WebTestCase
{
    public function testGuestBecomesHostOnPropertyCreation(): void
    {
        $client = static::createClient();
        
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un simple voyageur unique
        $email = 'test-guest-' . uniqid() . '@example.com';
        $guestUser = new User();
        $guestUser->setEmail($email);
        $guestUser->setPasswordHash('password');
        $guestUser->setPhone('+336' . random_int(10000000, 99999999));
        $guestUser->setStatus('active');
        $guestUser->setIsEmailVerified(true);
        $guestUser->setPreferredLanguage('fr');
        $guestUser->setPreferredCurrency('EUR');
        $guestUser->setAssignedRoles([]);
        
        $entityManager->persist($guestUser);
        $entityManager->flush();

        $this->assertNotContains('ROLE_HOST', $guestUser->getRoles());

        // Connecter l'utilisateur
        $client->loginUser($guestUser);

        // Aller sur la page de création
        $crawler = $client->request('GET', '/property/new');
        $this->assertResponseIsSuccessful();

        // Trouver une politique d'annulation pour le formulaire
        $policy = $entityManager->getRepository(CancellationPolicy::class)->findOneBy([]);
        $this->assertNotNull($policy);

        // Soumettre le formulaire
        $form = $crawler->selectButton('Ajouter le logement')->form([
            'host_property[title]' => 'Mon magnifique chalet test',
            'host_property[description]' => 'Super chalet au milieu des montagnes pour tests de rôles.',
            'host_property[cancellationPolicy]' => (string) $policy->getId(),
            'host_property[propertyType]' => 'chalet',
            'host_property[maxGuests]' => 4,
            'host_property[bedrooms]' => 2,
            'host_property[beds]' => 2,
            'host_property[bathrooms]' => 1,
            'host_property[pricePerNight]' => 120,
            'host_property[cleaningFee]' => 30,
            'host_property[securityDeposit]' => 150,
            'host_property[checkinTime]' => '16:00',
            'host_property[checkoutTime]' => '11:00',
        ]);

        $client->submit($form);

        // Doit rediriger vers le dashboard hôte
        $this->assertResponseRedirects('/host');
        
        // Rafraîchir l'utilisateur pour vérifier son nouveau rôle
        $entityManager->clear();
        $freshUser = $userRepository->find($guestUser->getId());
        $this->assertNotNull($freshUser);
        $this->assertContains('ROLE_HOST', $freshUser->getRoles());
    }

    public function testHostCannotAccessAdminPanel(): void
    {
        $client = static::createClient();
        
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $hostUser = $userRepository->findOneBy(['email' => 'jeanmarc.dupont@email.com']);
        $this->assertNotNull($hostUser);

        $client->loginUser($hostUser);

        // Essayer d'aller sur l'admin
        $client->request('GET', '/admin');
        
        // Doit renvoyer un Access Denied (403)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testHostCanConfirmReservation(): void
    {
        $client = static::createClient();
        
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Trouver une réservation en attente (pending)
        $reservation = $entityManager->getRepository(\App\Entity\Reservation::class)->findOneBy(['status' => 'pending']);
        $this->assertNotNull($reservation);

        $host = $reservation->getProperty()->getHost();
        $this->assertNotNull($host);

        // Connecter l'hôte
        $client->loginUser($host);

        // Aller sur la page de détail de la réservation
        $crawler = $client->request('GET', '/host/reservations/' . $reservation->getId());
        $this->assertResponseIsSuccessful();

        // Trouver et soumettre le formulaire de confirmation
        $form = $crawler->selectButton('Confirmer la réservation')->form();
        $client->submit($form);

        // Doit rediriger vers le détail de la réservation
        $this->assertResponseRedirects('/host/reservations/' . $reservation->getId());

        // Recharger la réservation depuis la BDD
        $entityManager->clear();
        $freshReservation = $entityManager->getRepository(\App\Entity\Reservation::class)->find($reservation->getId());
        $this->assertNotNull($freshReservation);
        $this->assertSame('confirmed', $freshReservation->getStatus());
        $this->assertNotNull($freshReservation->getUpdatedAt());
    }
}
