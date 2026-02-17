<?php

namespace App\Command;

use App\Domain\ResetPassword;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reset-password',
    description: 'Reset a user password'
)]
class ResetPasswordCommand extends Command
{
    public function __construct(
        protected ResetPassword $resetPassword
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        if (!$email) {
            $io->error('Email is required !');
        }

        $this->resetPassword->makeAPasswordRequest($email);

        $io->success('Email sent');

        return Command::SUCCESS;
    }
}
