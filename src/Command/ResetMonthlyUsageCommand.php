<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-monthly-usage',
    description: 'Réinitialise les quotas mensuels des utilisateurs',
)]
final class ResetMonthlyUsageCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->entityManager
            ->getRepository(User::class)
            ->findAll();

        $resetCount = 0;

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $user->resetMonthlyUsage();
            ++$resetCount;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Quotas mensuels réinitialisés pour %d utilisateur(s).',
            $resetCount
        ));

        return Command::SUCCESS;
    }
}
