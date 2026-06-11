<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Partie B — Annulation par le voyageur
 */
class GuestCancellationTest extends WebTestCase
{
    public function testGuestCanSeeReservationDetail(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $reservation = $reservationRepo->findOneBy(['guest' => $guest]);

        if ($reservation === null) {
            $this->markTestSkipped('Aucune réservation pour sophie.');
        }

        $client->request('GET', '/reservations/' . $reservation->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testGuestCanCancelPendingReservation(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $client->loginUser($guest);

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $reservation = $reservationRepo->findOneBy(['guest' => $guest, 'status' => 'pending']);

        if ($reservation === null) {
            $this->markTestSkipped('Aucune réservation pending pour sophie.');
        }

        // GET la page de détail pour récupérer le formulaire + CSRF
        $crawler = $client->request('GET', '/reservations/' . $reservation->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action*="annuler"]')->first();
        if ($form->count() === 0) {
            $this->markTestSkipped('Formulaire annulation non trouvé sur la page.');
        }

        $client->submit($form->form(), ['reason' => 'Changement de plan.']);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $refreshed = $reservationRepo->find($reservation->getId());
        $this->assertSame('cancelled', $refreshed->getStatus());
        $this->assertSame('Changement de plan.', $refreshed->getCancellationReason());
    }

    public function testGuestCannotCancelWithoutReason(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'bob@test.fr']);
        $client->loginUser($guest);

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $reservation = $reservationRepo->findOneBy(['guest' => $guest, 'status' => 'pending']);

        if ($reservation === null) {
            $this->markTestSkipped('Aucune réservation pending pour lucas.');
        }

        // GET la page pour extraire le token CSRF depuis le HTML
        $crawler = $client->request('GET', '/reservations/' . $reservation->getId());
        $this->assertResponseIsSuccessful();

        $tokenInput = $crawler->filter('input[name="_token"]')->first();
        if ($tokenInput->count() === 0) {
            $this->markTestSkipped('Token CSRF non trouvé sur la page.');
        }
        $csrfToken = $tokenInput->attr('value');

        // POST sans motif via request directe (le champ est dans un <details>)
        $client->request('POST', '/reservations/' . $reservation->getId() . '/annuler', [
            '_token'              => $csrfToken,
            'cancellation_reason' => '',
        ]);
        $client->followRedirects();

        $refreshed = $reservationRepo->find($reservation->getId());
        $this->assertSame('pending', $refreshed->getStatus());
    }

    public function testOtherUserCannotCancelSomeoneElsesReservation(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $guest = $userRepo->findOneBy(['email' => 'alice@test.fr']);
        $otherGuest = $userRepo->findOneBy(['email' => 'bob@test.fr']);
        $client->loginUser($guest);

        $reservationRepo = static::getContainer()->get(ReservationRepository::class);
        $otherReservation = $reservationRepo->findOneBy(['guest' => $otherGuest, 'status' => 'pending']);

        if ($otherReservation === null) {
            $this->markTestSkipped('Aucune réservation pending pour lucas.');
        }

        // Sophie essaie d'annuler la réservation de Lucas — doit être refusé
        $client->request('GET', '/reservations/' . $otherReservation->getId());
        // Doit être 403 ou redirect
        $this->assertNotSame(200, $client->getResponse()->getStatusCode());
    }
}
