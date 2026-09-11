<?php

namespace App\Tests\Unit;

use App\Dto\DvfRepairAudit;
use App\Service\DvfDepartmentRepairer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Command\RepairDvfDepartmentCommand;

final class DvfDepartmentRepairerTest extends TestCase
{
    private DvfDepartmentRepairer $repairer;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->repairer = new DvfDepartmentRepairer();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    #[DataProvider('validDepartments')]
    public function testDepartmentValidation(string $input, string $expected): void
    {
        self::assertSame($expected, DvfDepartmentRepairer::normalizeDepartment($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validDepartments(): iterable
    {
        yield 'metropole' => ['94', '94'];
        yield 'leading zero' => ['1', '01'];
        yield 'corsica' => ['2a', '2A'];
        yield 'drom' => ['971', '971'];
    }

    public function testInvalidDepartmentIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DvfDepartmentRepairer::normalizeDepartment('97');
    }

    public function testStreamingAuditAcceptsCompleteFixture(): void
    {
        $file = $this->createCsv($this->completeRows());
        $audit = $this->repairer->audit('94', $file);
        $this->temporaryFiles[] = $audit->stagingFile;

        self::assertTrue($audit->isValid());
        self::assertSame(12, $audit->sourceLines);
        self::assertSame(12, $audit->acceptedLines);
        self::assertSame([], $audit->missingYears);
        self::assertTrue($audit->vincennesPresent);
        self::assertSame('2025-06-15', $audit->vincennesMaxDate);
        self::assertSame(1, $audit->recentVincennesApartments);
    }

    public function testMissingYearInvalidatesAudit(): void
    {
        $rows = array_values(array_filter(
            $this->completeRows(),
            static fn(array $row): bool => $row[3] !== '2022-06-15',
        ));
        $audit = $this->repairer->audit('94', $this->createCsv($rows));
        $this->temporaryFiles[] = $audit->stagingFile;

        self::assertFalse($audit->isValid());
        self::assertSame([2022], $audit->missingYears);
    }

    public function testOutsideDepartmentIsCritical(): void
    {
        $rows = $this->completeRows();
        $rows[0][4] = '91';
        $rows[0][5] = '91001';
        $audit = $this->repairer->audit('94', $this->createCsv($rows));
        $this->temporaryFiles[] = $audit->stagingFile;

        self::assertFalse($audit->isValid());
        self::assertSame(1, $audit->rejectionReasons['outside_department']);
        self::assertNotEmpty($audit->criticalErrors);
    }

    public function testInvalidBusinessLineIsCounted(): void
    {
        $rows = $this->completeRows();
        $invalid = $rows[0];
        $invalid[8] = '0';
        $rows[] = $invalid;
        $audit = $this->repairer->audit('94', $this->createCsv($rows));
        $this->temporaryFiles[] = $audit->stagingFile;

        self::assertSame(13, $audit->sourceLines);
        self::assertSame(12, $audit->acceptedLines);
        self::assertSame(1, $audit->rejectedLines);
        self::assertSame(1, $audit->rejectionReasons['invalid_surface']);
    }

    public function testDuplicateSourceIdentifierInvalidatesAudit(): void
    {
        $rows = $this->completeRows();
        $duplicate = $rows[0];
        $duplicate[0] = $rows[1][0];
        $duplicate[1] = $rows[1][1];
        $duplicate[2] = $rows[1][2];
        $rows[] = $duplicate;
        $audit = $this->repairer->audit('94', $this->createCsv($rows));
        $this->temporaryFiles[] = $audit->stagingFile;

        self::assertSame(1, $audit->duplicateCounts['idmutinvar']);
        self::assertSame(1, $audit->duplicateCounts['source_identity']);
        self::assertFalse($audit->isValid());
    }

    public function testIncorrectFileIsRejected(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'agentboost_dvf_test_');
        self::assertIsString($file);
        $this->temporaryFiles[] = $file;
        file_put_contents($file, "foo,bar\n1,2\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->repairer->audit('94', $file);
    }

    public function testReplaceRefusesInvalidAuditBeforeUsingConnection(): void
    {
        $audit = new DvfRepairAudit(
            department: '94',
            sourceFile: 'fixture.csv',
            stagingFile: 'missing.sqlite',
            sourceLines: 0,
            acceptedLines: 0,
            rejectedLines: 0,
            rejectionReasons: [],
            minDate: null,
            maxDate: null,
            yearCounts: [],
            missingYears: range(2014, 2025),
            municipalityCount: 0,
            propertyTypeCount: 0,
            duplicateCounts: ['idmutinvar' => 0, 'idmutation' => 0, 'idopendata' => 0],
            criticalErrors: ['invalid'],
            vincennesPresent: false,
            vincennesMaxDate: null,
            recentVincennesApartments: 0,
        );
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('beginTransaction');

        $this->expectException(\RuntimeException::class);
        $this->repairer->replace($audit, $connection);
    }

    public function testDefaultCommandModeNeverUsesTargetConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('beginTransaction');
        $command = new RepairDvfDepartmentCommand($this->repairer, $connection);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'department' => '94',
            'csv' => $this->createCsv($this->completeRows()),
        ]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Mode audit uniquement', $tester->getDisplay());
    }

    public function testReplaceAffectsOnlyRequestedDepartmentInTemporaryDatabase(): void
    {
        $audit = $this->repairer->audit('94', $this->createCsv($this->completeRows()));
        $this->temporaryFiles[] = $audit->stagingFile;
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE comparable_sale (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                insee_code VARCHAR(10) NOT NULL,
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
        $connection->executeStatement(
            "INSERT INTO comparable_sale
                (insee_code, city, property_type, surface, price, price_per_sqm, sale_date, source)
             VALUES
                ('94080', NULL, 'UN APPARTEMENT', 80, 100000, 1250, '2017-01-01', 'old'),
                ('91001', NULL, 'UN APPARTEMENT', 80, 100000, 1250, '2025-01-01', 'keep')"
        );

        $result = $this->repairer->replace($audit, $connection);

        self::assertSame(1, $result['deleted']);
        self::assertSame(12, $result['inserted']);
        self::assertSame(12, (int) $connection->fetchOne("SELECT COUNT(*) FROM comparable_sale WHERE insee_code LIKE '94%'"));
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM comparable_sale WHERE insee_code LIKE '91%'"));
        self::assertSame('keep', $connection->fetchOne("SELECT source FROM comparable_sale WHERE insee_code LIKE '91%'"));
    }

    /** @return list<array<int, string>> */
    private function completeRows(): array
    {
        $rows = [];

        foreach (range(2014, 2025) as $year) {
            $rows[] = [
                (string) $year,
                'invariant-' . $year,
                'open-' . $year,
                sprintf('%d-06-15', $year),
                '94',
                '94080',
                'UN APPARTEMENT',
                '640000',
                '80',
                '0',
                '2.43',
                '48.85',
            ];
        }

        return $rows;
    }

    /** @param list<array<int, string>> $rows */
    private function createCsv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'agentboost_dvf_test_');
        self::assertIsString($file);
        $this->temporaryFiles[] = $file;
        $handle = fopen($file, 'wb');
        self::assertIsResource($handle);
        fputcsv($handle, [
            'idmutation', 'idmutinvar', 'idopendata', 'datemut', 'coddep',
            'l_codinsee', 'libtypbien', 'valeurfonc', 'sbatmai', 'sbatapt',
            'geompar_x', 'geompar_y',
        ], '|');

        foreach ($rows as $row) {
            fputcsv($handle, $row, '|');
        }

        fclose($handle);

        return $file;
    }
}
