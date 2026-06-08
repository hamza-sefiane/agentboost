<?php

namespace App\Command;

use App\Entity\CityReference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-city-reference',
    description: 'Importe les codes postaux, villes et codes INSEE dans city_reference',
)]
class ImportCityReferenceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('csvPath', InputArgument::REQUIRED, 'Chemin du fichier CSV Hexasmal / La Poste');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvPath = (string) $input->getArgument('csvPath');

        if (!is_file($csvPath)) {
            $io->error(sprintf('Fichier introuvable : %s', $csvPath));

            return Command::FAILURE;
        }

        $handle = fopen($csvPath, 'rb');

        if ($handle === false) {
            $io->error('Impossible d’ouvrir le fichier CSV.');

            return Command::FAILURE;
        }

        $this->em->getConnection()->executeStatement('DELETE FROM city_reference');

        $header = fgetcsv($handle, 0, ';');

        if ($header === false) {
            fclose($handle);
            $io->error('CSV vide ou invalide.');

            return Command::FAILURE;
        }

        $header = array_map(
            static fn(string $value): string => trim($value, " \t\n\r\0\x0B#"),
            $header
        );

        $inseeIndex = array_search('Code_commune_INSEE', $header, true);
        $cityIndex = array_search('Nom_de_la_commune', $header, true);
        $postalCodeIndex = array_search('Code_postal', $header, true);

        if ($inseeIndex === false || $cityIndex === false || $postalCodeIndex === false) {
            fclose($handle);
            $io->error('Colonnes attendues introuvables : Code_commune_INSEE, Nom_de_la_commune, Code_postal');

            return Command::FAILURE;
        }

        $imported = 0;
        $seen = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $inseeCode = trim((string) ($row[$inseeIndex] ?? ''));
            $city = trim((string) ($row[$cityIndex] ?? ''));
            $postalCode = trim((string) ($row[$postalCodeIndex] ?? ''));

            if ($inseeCode === '' || $city === '' || $postalCode === '') {
                continue;
            }

            $normalizedCity = $this->normalizeCity($city);
            $uniqueKey = $postalCode . '|' . $normalizedCity . '|' . $inseeCode;

            if (isset($seen[$uniqueKey])) {
                continue;
            }

            $seen[$uniqueKey] = true;

            $reference = (new CityReference())
                ->setPostalCode($postalCode)
                ->setCity($city)
                ->setNormalizedCity($normalizedCity)
                ->setInseeCode($inseeCode);

            $this->em->persist($reference);
            $imported++;

            if ($imported % 500 === 0) {
                $this->em->flush();
                $this->em->clear();
                $io->writeln(sprintf('%d communes importées...', $imported));
            }
        }

        fclose($handle);

        $this->em->flush();
        $this->em->clear();

        $io->success(sprintf('%d références communes importées.', $imported));

        return Command::SUCCESS;
    }

    private function normalizeCity(string $city): string
    {
        $city = mb_strtolower(trim($city));

        $city = str_replace(
            ['à', 'â', 'ä', 'ç', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ÿ', 'œ'],
            ['a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'oe'],
            $city
        );

        return preg_replace('/[^a-z0-9]+/', '-', $city) ?: $city;
    }
}
