<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CancellationPolicy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CancellationPolicyFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $policies = [
            [FixtureReferences::POLICY_FLEXIBLE, 'flexible', 'Flexible', 'Annulation gratuite jusqu\'à 24h avant l\'arrivée.'],
            [FixtureReferences::POLICY_MODERATE, 'moderate', 'Modérée', 'Remboursement intégral si annulation 5 jours avant l\'arrivée.'],
            [FixtureReferences::POLICY_STRICT, 'strict', 'Stricte', 'Remboursement de 50% si annulation 7 jours avant l\'arrivée.'],
        ];

        foreach ($policies as [$reference, $code, $label, $description]) {
            $policy = new CancellationPolicy();
            $policy->setCode($code);
            $policy->setLabel($label);
            $policy->setDescription($description);
            $manager->persist($policy);
            $this->addReference($reference, $policy);
        }

        $manager->flush();
    }
}
