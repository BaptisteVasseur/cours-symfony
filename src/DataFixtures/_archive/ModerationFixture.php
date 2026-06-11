<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AuditLog;
use App\Entity\Dispute;
use App\Entity\Property;
use App\Entity\Report;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ModerationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $guest3 = $this->getReference(FixtureReferences::USER_GUEST_3, User::class);
        $admin = $this->getReference(FixtureReferences::USER_ADMIN, User::class);
        $superAdmin = $this->getReference(FixtureReferences::USER_SUPER_ADMIN, User::class);
        $property1 = $this->getReference(FixtureReferences::PROPERTY_1, Property::class);
        $cancelled = $this->getReference(FixtureReferences::RESERVATION_CANCELLED, Reservation::class);
        $review = $this->getReference(FixtureReferences::REVIEW_1, Review::class);

        $reports = [
            [$guest3, 'property', $property1->getId(), 'Photos ne correspondant pas au logement réel', 'open'],
            [$admin, 'user', $guest3->getId(), 'Comportement inapproprié signalé par un hôte', 'investigating'],
            [$guest3, 'review', $review->getId(), 'Avis suspect, possible faux compte', 'closed'],
        ];

        foreach ($reports as [$reporter, $targetType, $targetId, $reason, $status]) {
            $report = new Report();
            $report->setReporter($reporter);
            $report->setTargetType($targetType);
            $report->setTarget($targetId);
            $report->setReason($reason);
            $report->setStatus($status);
            $manager->persist($report);
        }

        $dispute = new Dispute();
        $dispute->setReservation($cancelled);
        $dispute->setOpenedBy($guest3);
        $dispute->setStatus('open');
        $dispute->setResolution(null);
        $manager->persist($dispute);

        $disputeResolved = new Dispute();
        $disputeResolved->setReservation($this->getReference(FixtureReferences::RESERVATION_COMPLETED, Reservation::class));
        $disputeResolved->setOpenedBy($this->getReference(FixtureReferences::USER_GUEST_2, User::class));
        $disputeResolved->setStatus('resolved');
        $disputeResolved->setResolution('Remboursement partiel accordé suite à un problème de ménage.');
        $manager->persist($disputeResolved);

        $auditLogs = [
            [$superAdmin, 'user.login', 'User', $superAdmin->getId(), '192.168.1.10'],
            [$admin, 'property.approve', 'Property', $property1->getId(), '192.168.1.20'],
            [$admin, 'reservation.cancel', 'Reservation', $cancelled->getId(), '192.168.1.20'],
            [$superAdmin, 'user.suspend', 'User', $guest3->getId(), '192.168.1.10'],
            [null, 'system.cleanup', 'AuditLog', null, '127.0.0.1'],
        ];

        foreach ($auditLogs as [$user, $action, $entityType, $entityId, $ip]) {
            $auditLog = new AuditLog();
            $auditLog->setUser($user);
            $auditLog->setAction($action);
            $auditLog->setEntityType($entityType);
            if ($entityId !== null) {
                $auditLog->setEntity($entityId);
            } else {
                $auditLog->setEntity($superAdmin->getId());
            }
            $auditLog->setIpAddress($ip);
            $manager->persist($auditLog);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            PropertyFixture::class,
            ReservationFixture::class,
            ReviewFixture::class,
        ];
    }
}
