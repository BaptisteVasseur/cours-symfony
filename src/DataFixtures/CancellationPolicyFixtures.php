<?php

namespace App\DataFixtures;

use App\Entity\CancellationPolicy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CancellationPolicyFixtures extends Fixture
{
    private const POLICIES = [
        [
            'code'        => 'flexible',
            'label'       => 'Flexible',
            'description' => 'Remboursement intégral jusqu\'à 24h avant l\'arrivée.',
            'ref'         => 'policy_flexible',
        ],
        [
            'code'        => 'moderate',
            'label'       => 'Modérée',
            'description' => 'Remboursement intégral jusqu\'à 5 jours avant l\'arrivée.',
            'ref'         => 'policy_moderate',
        ],
        [
            'code'        => 'strict',
            'label'       => 'Stricte',
            'description' => 'Remboursement de 50% jusqu\'à 1 semaine avant l\'arrivée.',
            'ref'         => 'policy_strict',
        ],
        [
            'code'        => 'non_refundable',
            'label'       => 'Non remboursable',
            'description' => 'Aucun remboursement en cas d\'annulation.',
            'ref'         => 'policy_non_refundable',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::POLICIES as $data) {
            $policy = new CancellationPolicy();
            $policy->setCode($data['code']);
            $policy->setLabel($data['label']);
            $policy->setDescription($data['description']);

            $manager->persist($policy);
            $this->addReference($data['ref'], $policy);
        }

        $manager->flush();
    }
}