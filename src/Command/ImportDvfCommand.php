<?php

namespace App\Command;

use App\Entity\ComparableSale;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-dvf',
    description: 'Importe un fichier DVF+ CSV dans la base locale',
)]
class ImportDvfCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Chemin du fichier CSV DVF+'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');

        if (!is_file($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return Command::FAILURE;
        }

        $handle = fopen($file, 'rb');

        if (!$handle) {
            $io->error('Impossible d’ouvrir le fichier.');

            return Command::FAILURE;
        }

        $header = fgetcsv($handle, 0, '|');

        if (!$header) {
            $io->error('Impossible de lire l’entête du fichier.');

            return Command::FAILURE;
        }

        $count = 0;

        while (($row = fgetcsv($handle, 0, '|')) !== false) {
            $data = array_combine($header, $row);

            if (!$data) {
                continue;
            }

            $surface = (int) round(
                max(
                    (float) ($data['sbatmai'] ?? 0),
                    (float) ($data['sbatapt'] ?? 0)
                )
            );

            $price = (int) round((float) ($data['valeurfonc'] ?? 0));

            if ($surface <= 0 || $price <= 0) {
                continue;
            }

            $sale = new ComparableSale();

            $sale->setInseeCode((string) ($data['l_codinsee'] ?? ''));
            $sale->setCity(null);
            $sale->setPropertyType((string) ($data['libtypbien'] ?? ''));
            $sale->setSurface($surface);
            $sale->setPrice($price);
            $sale->setPricePerSqm((int) round($price / $surface));
            $sale->setSaleDate(new \DateTime((string) $data['datemut']));
            $sale->setX(
                !empty($data['geompar_x'])
                    ? (float) $data['geompar_x']
                    : null
            );
            $sale->setY(
                !empty($data['geompar_y'])
                    ? (float) $data['geompar_y']
                    : null
            );
            $sale->setSource('DVF+');

            $this->entityManager->persist($sale);

            ++$count;

            if ($count % 200 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                gc_collect_cycles();

                $io->writeln(sprintf('%d lignes importées...', $count));
            }
        }

        fclose($handle);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d ventes DVF importées avec succès.',
            $count
        ));

        return Command::SUCCESS;
    }
}
