<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            [FixtureReferences::ROLE_HOST, 'ROLE_HOST', 'Hôte'],
            [FixtureReferences::ROLE_ADMIN, 'ROLE_ADMIN', 'Administrateur'],
            [FixtureReferences::ROLE_SUPER_ADMIN, 'ROLE_SUPER_ADMIN', 'Super Administrateur'],
        ];

        foreach ($roles as [$reference, $code, $label]) {
            $role = new Role();
            $role->setCode($code);
            $role->setLabel($label);
            $manager->persist($role);
            $this->addReference($reference, $role);
        }

        $manager->flush();
    }
}
