<?php

declare(strict_types=1);

namespace App\Tests\Controller\Front;

use App\Entity\CancellationPolicy;
use App\Entity\User;
use App\Repository\CancellationPolicyRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HostPropertyControllerTest extends WebTestCase
{
    public function testGuestCanPublishPropertyAndBecomesHost(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $policyRepository = static::getContainer()->get(CancellationPolicyRepository::class);

        $guest = $userRepository->findOneBy(['email' => 'sophie.chen@email.com']);
        if (!$guest instanceof User) {
            self::markTestSkipped('Utilisateur sophie.chen@email.com introuvable. Lancez les fixtures.');
        }

        $policy = $policyRepository->findOneBy([]);
        if (!$policy instanceof CancellationPolicy) {
            self::markTestSkipped('Aucune politique d\'annulation en base. Lancez les fixtures.');
        }

        $guest->setAssignedRoles([]);
        static::getContainer()->get('doctrine')->getManager()->flush();

        $client->loginUser($guest);
        $crawler = $client->request('GET', '/compte/proprietes/nouvelle');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Publier l\'annonce')->form([
            'host_property[title]' => 'Studio test publication',
            'host_property[description]' => 'Description suffisamment longue pour passer la validation.',
            'host_property[propertyType]' => 'apartment',
            'host_property[cancellationPolicy]' => (string) $policy->getId(),
            'host_property[maxGuests]' => '2',
            'host_property[bedrooms]' => '1',
            'host_property[beds]' => '1',
            'host_property[bathrooms]' => '1',
            'host_property[pricePerNight]' => '75.00',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/compte/proprietes');

        $guest = $userRepository->find($guest->getId());
        self::assertNotNull($guest);
        $client->loginUser($guest);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Vous êtes maintenant hôte');

        self::assertTrue($guest->hasAssignedRole('ROLE_HOST'));
    }
}
