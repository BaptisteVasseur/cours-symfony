<?php

declare(strict_types=1);

namespace App\SecondayPorts;

use App\Domain\DTO\UserRegistrationDTO;
use App\Domain\SecondaryPorts\DatabaseServiceInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DatabaseService implements DatabaseServiceInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {}

    public function findByEmail(string $email)
    {
        $userRepository = $this->entityManager->getRepository(User::class);

        return $userRepository->findOneBy(['email' => $email]);
    }

    public function save($user)
    {
        $this->entityManager->flush();
    }

    public function createUserFromDTO(UserRegistrationDTO $userRegistrationDTO)
    {
        // TODO: Implement createUserFromDTO() method.
    }
}
