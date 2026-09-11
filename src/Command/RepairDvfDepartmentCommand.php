<?php

namespace App\Command;

use App\Dto\DvfRepairAudit;
use App\Service\DvfDepartmentRepairer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:repair-dvf-department',
    description: 'Audite et, sur demande explicite, remplace les ventes DVF+ d’un département.',
)]
final class RepairDvfDepartmentCommand extends Command
{
    public function __construct(
        private readonly DvfDepartmentRepairer $repairer,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('department', InputArgument::REQUIRED, 'Code département (94, 2A, 971, etc.)')
            ->addArgument('csv', InputArgument::REQUIRED, 'Chemin du fichier CSV DVF+ départemental')
            ->addOption('audit-only', null, InputOption::VALUE_NONE, 'Audite le fichier sans modifier comparable_sale (comportement par défaut)')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Remplace transactionnellement le département après un audit valide');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('audit-only') && $input->getOption('replace')) {
            $io->error('Les options --audit-only et --replace sont mutuellement exclusives.');

            return Command::INVALID;
        }

        $audit = null;

        try {
            $audit = $this->repairer->audit(
                (string) $input->getArgument('department'),
                (string) $input->getArgument('csv'),
            );
            $this->displayAudit($io, $audit);

            if (!$input->getOption('replace')) {
                $io->note('Mode audit uniquement : comparable_sale n’a pas été modifiée.');

                return $audit->isValid() ? Command::SUCCESS : Command::FAILURE;
            }

            if (!$audit->isValid()) {
                $io->error('Remplacement refusé : corrigez toutes les erreurs de l’audit.');

                return Command::FAILURE;
            }

            $result = $this->repairer->replace($audit, $this->connection);
            $io->success(sprintf(
                'Département %s remplacé : %d lignes supprimées, %d insérées (total %d → %d).',
                $audit->department,
                $result['deleted'],
                $result['inserted'],
                $result['totalBefore'],
                $result['totalAfter'],
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            if ($audit instanceof DvfRepairAudit) {
                $this->repairer->removeStaging($audit);
            }
        }
    }

    private function displayAudit(SymfonyStyle $io, DvfRepairAudit $audit): void
    {
        $io->title('Audit de réparation DVF+ départementale');
        $io->definitionList(
            ['Département' => $audit->department],
            ['Fichier' => $audit->sourceFile],
            ['Lignes sources' => number_format($audit->sourceLines, 0, ',', ' ')],
            ['Lignes acceptées' => number_format($audit->acceptedLines, 0, ',', ' ')],
            ['Lignes rejetées' => number_format($audit->rejectedLines, 0, ',', ' ')],
            ['Date minimale' => $audit->minDate ?? '—'],
            ['Date maximale' => $audit->maxDate ?? '—'],
            ['Communes' => $audit->municipalityCount],
            ['Types de biens' => $audit->propertyTypeCount],
            ['Audit valide' => $audit->isValid() ? 'oui' : 'non'],
        );

        $io->section('Couverture annuelle');
        $io->table(
            ['Année', 'Lignes acceptées'],
            array_map(
                static fn(int $year, int $count): array => [$year, number_format($count, 0, ',', ' ')],
                array_keys($audit->yearCounts),
                array_values($audit->yearCounts),
            ),
        );

        $io->definitionList(
            ['Années manquantes' => $audit->missingYears === [] ? 'aucune' : implode(', ', $audit->missingYears)],
            ['Doublons idmutinvar' => $audit->duplicateCounts['idmutinvar']],
            ['Doublons idmutation' => $audit->duplicateCounts['idmutation']],
            ['Doublons idopendata' => $audit->duplicateCounts['idopendata']],
            ['Doublons identitÃ© source complÃ¨te' => $audit->duplicateCounts['source_identity']],
        );

        if ($audit->rejectionReasons !== []) {
            $io->section('Rejets');
            $io->table(
                ['Raison', 'Nombre'],
                array_map(
                    static fn(string $reason, int $count): array => [$reason, $count],
                    array_keys($audit->rejectionReasons),
                    array_values($audit->rejectionReasons),
                ),
            );
        }

        if ($audit->department === '94') {
            $io->section('Contrôle Vincennes (94080)');
            $io->definitionList(
                ['Présente' => $audit->vincennesPresent ? 'oui' : 'non'],
                ['Date maximale' => $audit->vincennesMaxDate ?? '—'],
                ['Appartements 2025, 64–96 m², 1 000–15 000 €/m²' => $audit->recentVincennesApartments],
            );
        }

        if ($audit->criticalErrors !== []) {
            $io->section('Erreurs critiques');
            $io->listing($audit->criticalErrors);
        }
    }
}
