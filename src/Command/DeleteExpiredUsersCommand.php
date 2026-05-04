<?php

namespace App\Command;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:delete-expired-users',
    description: 'Supprime les utilisateurs dont la période est expirée',
)]
class DeleteExpiredUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();

        $users = $this->em->getRepository(User::class)->findBy([
            'deleteAtPeriodEnd' => true,
        ]);

        $count = 0;

        foreach ($users as $user) {
            if ($user->getNextBillingDate() === null || $user->getNextBillingDate() > $now) {
                continue;
            }

            $properties = $this->em->getRepository(Property::class)->findBy([
                'owner' => $user,
            ]);

            foreach ($properties as $property) {
                $this->em->remove($property);
            }

            $this->em->remove($user);
            $count++;
        }

        $this->em->flush();

        $output->writeln(sprintf('✅ %d utilisateur(s) supprimé(s)', $count));

        return Command::SUCCESS;
    }
}