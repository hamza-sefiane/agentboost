<?php

namespace App\Command;

use App\Service\DvfNeonExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-dvf-neon',
    description: 'Exporte des ventes DVF locales vers un CSV compatible avec PostgreSQL COPY.',
)]
final class ExportDvfNeonCommand extends Command
{
    public function __construct(private readonly DvfNeonExporter $exporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'departments',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Départements à exporter (ex. 01 91 94 2A 972)',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Chemin du fichier CSV à créer',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = $input->getOption('output');

        if (!is_string($outputPath) || trim($outputPath) === '') {
            $io->error('L’option --output est obligatoire.');

            return Command::INVALID;
        }

        try {
            /** @var list<string> $departments */
            $departments = $input->getArgument('departments');
            $result = $this->exporter->export($departments, $outputPath);

            $io->success(sprintf('%s lignes DVF exportées.', number_format($result->totalRows, 0, ',', ' ')));
            $io->table(
                ['Département', 'Lignes'],
                array_map(
                    static fn(string $department, int $count): array => [
                        $department,
                        number_format($count, 0, ',', ' '),
                    ],
                    array_keys($result->countsByDepartment),
                    array_values($result->countsByDepartment),
                ),
            );
            $io->definitionList(
                ['Fichier' => $result->outputPath],
                ['Taille' => $this->formatBytes($result->fileSize)],
            );

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' octets';
        }

        return number_format($bytes / 1024 / 1024, 2, ',', ' ') . ' Mio';
    }
}
