<?php

namespace App\Tests\Unit;

use App\Service\DvfNeonExporter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DvfNeonExporterTest extends TestCase
{
    private Connection $connection;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE comparable_sale (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                insee_code VARCHAR(30) NOT NULL,
                city VARCHAR(120),
                property_type VARCHAR(80) NOT NULL,
                surface INTEGER NOT NULL,
                price BIGINT NOT NULL,
                price_per_sqm INTEGER NOT NULL,
                sale_date DATE NOT NULL,
                x DOUBLE PRECISION,
                y DOUBLE PRECISION,
                source VARCHAR(50) NOT NULL
            )'
        );

        $this->addSale('01001', 'Ain', 'UNE MAISON', '2025-01-02', null);
        $this->addSale('2A004', 'Ajaccio', 'UN APPARTEMENT', '2025-01-03', 8.73);
        $this->addSale('2B033', 'Bastia', 'UN APPARTEMENT', '2025-01-04', 9.45);
        $this->addSale('97209', 'Fort-de-France', 'UN APPARTEMENT', '2025-01-05', -61.06);
        $this->addSale('97302', 'Cayenne', 'UNE MAISON', '2025-01-06', -52.33);
        $this->addSale('97411', 'Saint-Denis', 'UN APPARTEMENT', '2025-01-07', 55.45);
        $this->addSale('94080', 'Vincennes, centre', 'UN APPARTEMENT', '2025-01-08', 2.43);
    }

    protected function tearDown(): void
    {
        $this->connection->close();

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testFiltersDepartment01(): void
    {
        $rows = $this->exportAndRead(['1']);

        self::assertCount(2, $rows);
        self::assertSame('01001', $rows[1][0]);
    }

    public function testFiltersCorsicanDepartments(): void
    {
        $rows = $this->exportAndRead(['2b', '2A']);

        self::assertSame(['2A004', '2B033'], array_column(array_slice($rows, 1), 0));
    }

    public function testFiltersDromDepartmentsWithoutCollapsingThemInto97(): void
    {
        $rows = $this->exportAndRead(['972', '973', '974']);

        self::assertSame(['97209', '97302', '97411'], array_column(array_slice($rows, 1), 0));
    }

    public function testHeaderHasExactColumnOrderAndNoId(): void
    {
        $rows = $this->exportAndRead(['94']);

        self::assertSame(DvfNeonExporter::COLUMNS, $rows[0]);
        self::assertNotContains('id', $rows[0], true);
    }

    public function testCsvEscapingAndPostgresqlCompatibleNull(): void
    {
        $path = $this->newOutputPath();
        (new DvfNeonExporter($this->connection))->export(['94'], $path);
        $rows = $this->readCsv($path);
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringContainsString('"Vincennes, centre"', $contents);
        self::assertSame('', $rows[1][8]);
        self::assertCount(10, $rows[1]);
    }

    /** @param list<string> $departments
     *  @return list<list<string|null>>
     */
    private function exportAndRead(array $departments): array
    {
        $path = $this->newOutputPath();
        (new DvfNeonExporter($this->connection))->export($departments, $path);

        return $this->readCsv($path);
    }

    private function newOutputPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agentboost_export_target_');
        self::assertIsString($path);
        unlink($path);
        $this->files[] = $path;

        return $path;
    }

    /** @return list<list<string|null>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        self::assertIsResource($handle);
        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function addSale(string $insee, ?string $city, string $type, string $date, ?float $x): void
    {
        $this->connection->insert('comparable_sale', [
            'insee_code' => $insee,
            'city' => $city,
            'property_type' => $type,
            'surface' => 80,
            'price' => 640000,
            'price_per_sqm' => 8000,
            'sale_date' => $date,
            'x' => $x,
            'y' => null,
            'source' => 'DVF+',
        ]);
    }
}
