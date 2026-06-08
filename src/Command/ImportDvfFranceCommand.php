<?php

namespace App\Command;

use App\Entity\ComparableSale;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-dvf-france',
    description: 'Importe tous les fichiers DVF+ d’un dossier en ignorant les départements déjà présents',
)]
class ImportDvfFranceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Dossier contenant les dvf_plus_dXX.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = rtrim((string) $input->getArgument('directory'), '\\/');

        if (!is_dir($directory)) {
            $io->error(sprintf('Dossier introuvable : %s', $directory));
            return Command::FAILURE;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . 'dvf_plus_d*.csv');

        if (!$files) {
            $io->error('Aucun fichier DVF trouvé.');
            return Command::FAILURE;
        }

        sort($files);

        $existingDepartments = $this->getExistingDepartments();
        $totalImported = 0;

        foreach ($files as $file) {
            $department = $this->extractDepartmentFromFilename($file);

            if ($department === null) {
                $io->warning(sprintf('Fichier ignoré, département introuvable : %s', basename($file)));
                continue;
            }

            if (isset($existingDepartments[$department])) {
                $io->note(sprintf('Département %s ignoré : déjà présent en base.', $department));
                continue;
            }

            $imported = $this->importFile($file, $io, $totalImported);

            if ($imported > 0) {
                $existingDepartments[$department] = true;
            }

            $io->success(sprintf('%s : %d ventes importées', basename($file), $imported));
        }

        $io->success(sprintf('Import France terminé : %d ventes importées.', $totalImported));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function getExistingDepartments(): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT substr(insee_code, 1, 2) AS dep FROM comparable_sale WHERE insee_code IS NOT NULL AND insee_code != ""'
        );

        $departments = [];

        foreach ($rows as $row) {
            $dep = (string) ($row['dep'] ?? '');

            if ($dep !== '') {
                $departments[$dep] = true;
            }
        }

        return $departments;
    }

    private function extractDepartmentFromFilename(string $file): ?string
    {
        if (preg_match('/dvf_plus_d([0-9]{2}[ab]?)/i', basename($file), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    private function importFile(string $file, SymfonyStyle $io, int &$totalImported): int
    {
        $io->section(basename($file));

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            $io->warning(sprintf('Impossible de lire %s', $file));
            return 0;
        }

        $header = fgetcsv($handle, 0, '|');

        if ($header === false) {
            fclose($handle);
            return 0;
        }

        $imported = 0;

        while (($row = fgetcsv($handle, 0, '|')) !== false) {
            $data = array_combine($header, $row);

            if (!$data) {
                continue;
            }

            $surface = (int) round(max(
                (float) ($data['sbatmai'] ?? 0),
                (float) ($data['sbatapt'] ?? 0)
            ));

            $price = (int) round((float) ($data['valeurfonc'] ?? 0));
            $inseeCode = trim((string) ($data['l_codinsee'] ?? ''));
            $propertyType = trim((string) ($data['libtypbien'] ?? ''));
            $saleDate = trim((string) ($data['datemut'] ?? ''));

            if ($surface <= 0 || $price <= 0 || $inseeCode === '' || $propertyType === '' || $saleDate === '') {
                continue;
            }

            try {
                $sale = new ComparableSale();
                $sale->setInseeCode($inseeCode);
                $sale->setCity(null);
                $sale->setPropertyType($propertyType);
                $sale->setSurface($surface);
                $sale->setPrice($price);
                $sale->setPricePerSqm((int) round($price / $surface));
                $sale->setSaleDate(new \DateTime($saleDate));
                $sale->setX(!empty($data['geompar_x']) ? (float) $data['geompar_x'] : null);
                $sale->setY(!empty($data['geompar_y']) ? (float) $data['geompar_y'] : null);
                $sale->setSource('DVF+');

                $this->entityManager->persist($sale);

                ++$imported;
                ++$totalImported;

                if ($totalImported % 500 === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    gc_collect_cycles();

                    if ($totalImported % 5000 === 0) {
                        $io->writeln(sprintf('%d ventes importées...', $totalImported));
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        fclose($handle);

        $this->entityManager->flush();
        $this->entityManager->clear();
        gc_collect_cycles();

        return $imported;
    }
}
