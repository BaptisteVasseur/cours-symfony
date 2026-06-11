<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use App\Entity\UserRole;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:role',
    description: 'Assigne ou retire un rôle à un utilisateur',
)]
class UserRoleCommand extends Command
{
    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_CHEH'];

    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('role', InputArgument::REQUIRED, 'Rôle à assigner (' . implode(', ', self::ALLOWED_ROLES) . ')')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, 'Retirer le rôle au lieu de l\'assigner')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email  = $input->getArgument('email');
        $roleCode = strtoupper($input->getArgument('role'));
        $remove = $input->getOption('remove');

        if (!in_array($roleCode, self::ALLOWED_ROLES, true)) {
            $io->error(sprintf('Rôle "%s" invalide. Disponibles : %s', $roleCode, implode(', ', self::ALLOWED_ROLES)));
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" introuvable.', $email));
            return Command::FAILURE;
        }

        $role = $this->roleRepository->findOneBy(['code' => $roleCode]);
        if (!$role) {
            $role = new Role();
            $role->setCode($roleCode);
            $role->setLabel(match ($roleCode) {
                'ROLE_ADMIN' => 'Administrateur',
                'ROLE_CHEH'  => 'Cheh',
                default      => 'Utilisateur',
            });
            $this->em->persist($role);
        }

        if ($remove) {
            foreach ($user->getUserRoles() as $userRole) {
                if ($userRole->getRole()->getCode() === $roleCode) {
                    $user->removeUserRole($userRole);
                    $this->em->remove($userRole);
                    $this->em->flush();
                    $io->success(sprintf('Rôle %s retiré à %s.', $roleCode, $email));
                    return Command::SUCCESS;
                }
            }
            $io->warning(sprintf('L\'utilisateur %s n\'a pas le rôle %s.', $email, $roleCode));
            return Command::SUCCESS;
        }

        foreach ($user->getUserRoles() as $userRole) {
            if ($userRole->getRole()->getCode() === $roleCode) {
                $io->warning(sprintf('L\'utilisateur %s a déjà le rôle %s.', $email, $roleCode));
                return Command::SUCCESS;
            }
        }

        $userRole = new UserRole();
        $userRole->setUser($user);
        $userRole->setRole($role);
        $this->em->persist($userRole);
        $this->em->flush();

        $io->success(sprintf('Rôle %s assigné à %s.', $roleCode, $email));

        return Command::SUCCESS;
    }
}
