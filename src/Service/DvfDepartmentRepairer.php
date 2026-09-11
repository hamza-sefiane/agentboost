<?php

namespace App\Service;

use App\Dto\DvfRepairAudit;
use Doctrine\DBAL\Connection;

final class DvfDepartmentRepairer
{
    private const EXPECTED_START_YEAR = 2014;
    private const EXPECTED_END_YEAR = 2025;

    private const REQUIRED_COLUMNS = [
        'idmutation',
        'idmutinvar',
        'idopendata',
        'datemut',
        'coddep',
        'l_codinsee',
        'libtypbien',
        'valeurfonc',
        'sbatmai',
        'sbatapt',
        'geompar_x',
        'geompar_y',
    ];

    public function audit(string $department, string $sourceFile): DvfRepairAudit
    {
        $department = self::normalizeDepartment($department);

        if (!is_file($sourceFile) || !is_readable($sourceFile)) {
            throw new \InvalidArgumentException(sprintf('Fichier CSV introuvable ou illisible : %s', $sourceFile));
        }

        $handle = fopen($sourceFile, 'rb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir le fichier CSV : %s', $sourceFile));
        }

        $stagingFile = tempnam(sys_get_temp_dir(), 'agentboost_dvf_stage_');

        if ($stagingFile === false) {
            fclose($handle);
            throw new \RuntimeException('Impossible de créer le fichier de staging temporaire.');
        }

        try {
            $staging = $this->createStagingDatabase($stagingFile);
            $header = fgetcsv($handle, 0, '|');

            if ($header === false || count($header) < 2) {
                throw new \InvalidArgumentException('Entête CSV absente ou séparateur « | » invalide.');
            }

            $header = array_map(static fn(string $column): string => trim($column, "\xEF\xBB\xBF \t\r\n"), $header);
            $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, $header));

            if ($missingColumns !== []) {
                throw new \InvalidArgumentException('Colonnes CSV manquantes : ' . implode(', ', $missingColumns));
            }

            $indexes = array_flip($header);
            $insert = $staging->prepare(
                'INSERT INTO staged_sale (
                    source_line, idmutinvar, idmutation, idopendata, department,
                    insee_code, property_type, surface, price, price_per_sqm,
                    sale_date, x, y, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $sourceLines = 0;
            $acceptedLines = 0;
            $rejectedLines = 0;
            $rejectionReasons = [];
            $criticalErrors = [];

            $staging->beginTransaction();

            while (($row = fgetcsv($handle, 0, '|')) !== false) {
                ++$sourceLines;
                $sourceLine = $sourceLines + 1;

                if (count($row) !== count($header)) {
                    $this->reject($rejectionReasons, 'column_count_mismatch');
                    $this->addCriticalError($criticalErrors, sprintf('Ligne %d : nombre de colonnes invalide.', $sourceLine));
                    ++$rejectedLines;
                    continue;
                }

                $value = static fn(string $column): string => trim((string) $row[$indexes[$column]]);
                $rowDepartment = self::normalizeDepartmentFromSource($value('coddep'));
                $inseeCode = $value('l_codinsee');

                if ($rowDepartment !== $department || !self::inseeBelongsToDepartment($inseeCode, $department)) {
                    $this->reject($rejectionReasons, 'outside_department');
                    $this->addCriticalError($criticalErrors, sprintf(
                        'Ligne %d : département incohérent (coddep=%s, insee=%s).',
                        $sourceLine,
                        $value('coddep'),
                        $inseeCode,
                    ));
                    ++$rejectedLines;
                    continue;
                }

                $surface = (int) round(max(
                    $this->parseNumber($value('sbatmai')),
                    $this->parseNumber($value('sbatapt')),
                ));
                $price = (int) round($this->parseNumber($value('valeurfonc')));
                $propertyType = $value('libtypbien');
                $saleDate = $this->parseDate($value('datemut'));

                $reason = match (true) {
                    $surface <= 0 => 'invalid_surface',
                    $price <= 0 => 'invalid_price',
                    $inseeCode === '' => 'missing_insee_code',
                    $propertyType === '' => 'missing_property_type',
                    $value('datemut') === '' => 'missing_sale_date',
                    $saleDate === null => 'invalid_sale_date',
                    default => null,
                };

                if ($reason !== null) {
                    $this->reject($rejectionReasons, $reason);
                    ++$rejectedLines;

                    if ($reason === 'invalid_sale_date') {
                        $this->addCriticalError($criticalErrors, sprintf('Ligne %d : date invalide « %s ».', $sourceLine, $value('datemut')));
                    }

                    continue;
                }

                $insert->execute([
                    $sourceLine,
                    $value('idmutinvar'),
                    $value('idmutation'),
                    $value('idopendata'),
                    $department,
                    $inseeCode,
                    $propertyType,
                    $surface,
                    $price,
                    (int) round($price / $surface),
                    $saleDate,
                    $this->parseNullableNumber($value('geompar_x')),
                    $this->parseNullableNumber($value('geompar_y')),
                    'DVF+',
                ]);
                ++$acceptedLines;
            }

            $staging->commit();

            return $this->buildAudit(
                $staging,
                $department,
                $sourceFile,
                $stagingFile,
                $sourceLines,
                $acceptedLines,
                $rejectedLines,
                $rejectionReasons,
                array_values(array_unique($criticalErrors)),
            );
        } catch (\Throwable $exception) {
            @unlink($stagingFile);
            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{deleted: int, inserted: int, totalBefore: int, totalAfter: int}
     */
    public function replace(DvfRepairAudit $audit, Connection $connection): array
    {
        if (!$audit->isValid()) {
            throw new \RuntimeException('Remplacement refusé : l’audit du staging n’est pas valide.');
        }

        if (!is_file($audit->stagingFile)) {
            throw new \RuntimeException('Remplacement refusé : le staging est introuvable.');
        }

        $staging = new \PDO('sqlite:' . $audit->stagingFile, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $predicate = $this->departmentPredicate($audit->department);
        $totalBefore = (int) $connection->fetchOne('SELECT COUNT(*) FROM comparable_sale');
        $departmentBefore = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM comparable_sale WHERE ' . $predicate['sql'],
            $predicate['params'],
        );

        $connection->beginTransaction();

        try {
            $deleted = $connection->executeStatement(
                'DELETE FROM comparable_sale WHERE ' . $predicate['sql'],
                $predicate['params'],
            );
            $inserted = 0;
            $statement = $connection->prepare(
                'INSERT INTO comparable_sale (
                    insee_code, city, property_type, surface, price,
                    price_per_sqm, sale_date, x, y, source
                ) VALUES (
                    :insee_code, :city, :property_type, :surface, :price,
                    :price_per_sqm, :sale_date, :x, :y, :source
                )'
            );

            $rows = $staging->query(
                'SELECT insee_code, property_type, surface, price, price_per_sqm, sale_date, x, y, source
                 FROM staged_sale ORDER BY source_line'
            );

            while (($row = $rows->fetch(\PDO::FETCH_ASSOC)) !== false) {
                foreach ([
                    'insee_code' => $row['insee_code'],
                    'city' => null,
                    'property_type' => $row['property_type'],
                    'surface' => $row['surface'],
                    'price' => $row['price'],
                    'price_per_sqm' => $row['price_per_sqm'],
                    'sale_date' => $row['sale_date'],
                    'x' => $row['x'],
                    'y' => $row['y'],
                    'source' => $row['source'],
                ] as $parameter => $value) {
                    $statement->bindValue($parameter, $value);
                }
                $statement->executeStatement();
                ++$inserted;
            }

            if ($inserted !== $audit->acceptedLines) {
                throw new \RuntimeException(sprintf(
                    'Volume inséré incohérent : %d au lieu de %d.',
                    $inserted,
                    $audit->acceptedLines,
                ));
            }

            $departmentAfter = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM comparable_sale WHERE ' . $predicate['sql'],
                $predicate['params'],
            );
            $dates = $connection->fetchAssociative(
                'SELECT MIN(sale_date) AS min_date, MAX(sale_date) AS max_date
                 FROM comparable_sale WHERE ' . $predicate['sql'],
                $predicate['params'],
            );
            $totalAfter = (int) $connection->fetchOne('SELECT COUNT(*) FROM comparable_sale');

            if (
                $departmentAfter !== $audit->acceptedLines
                || $totalAfter !== $totalBefore - $departmentBefore + $audit->acceptedLines
                || ($dates['min_date'] ?? null) !== $audit->minDate
                || ($dates['max_date'] ?? null) !== $audit->maxDate
            ) {
                throw new \RuntimeException('Les contrôles post-remplacement ont échoué.');
            }

            $connection->commit();

            return [
                'deleted' => $deleted,
                'inserted' => $inserted,
                'totalBefore' => $totalBefore,
                'totalAfter' => $totalAfter,
            ];
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function removeStaging(DvfRepairAudit $audit): void
    {
        if (is_file($audit->stagingFile)) {
            @unlink($audit->stagingFile);
        }
    }

    public static function normalizeDepartment(string $department): string
    {
        $department = strtoupper(trim($department));

        if (preg_match('/^[1-9]$/', $department) === 1) {
            $department = '0' . $department;
        }

        if (preg_match('/^(?:0[1-9]|[1-8][0-9]|9[0-5]|2A|2B|97[1-6])$/', $department) !== 1) {
            throw new \InvalidArgumentException(sprintf('Code département invalide : %s', $department));
        }

        return $department;
    }

    public static function inseeBelongsToDepartment(string $inseeCode, string $department): bool
    {
        $inseeCode = strtoupper(trim($inseeCode));
        $department = self::normalizeDepartment($department);

        return str_starts_with($inseeCode, $department);
    }

    private static function normalizeDepartmentFromSource(string $department): string
    {
        $department = strtoupper(trim($department));

        if (preg_match('/^[1-9]$/', $department) === 1) {
            return '0' . $department;
        }

        return $department;
    }

    private function createStagingDatabase(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA journal_mode = DELETE');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec(
            'CREATE TABLE staged_sale (
                source_line INTEGER NOT NULL,
                idmutinvar TEXT,
                idmutation TEXT,
                idopendata TEXT,
                department TEXT NOT NULL,
                insee_code TEXT NOT NULL,
                property_type TEXT NOT NULL,
                surface INTEGER NOT NULL,
                price INTEGER NOT NULL,
                price_per_sqm INTEGER NOT NULL,
                sale_date TEXT NOT NULL,
                x REAL,
                y REAL,
                source TEXT NOT NULL
            )'
        );

        return $pdo;
    }

    /**
     * @param array<string, int> $rejectionReasons
     * @param list<string>       $criticalErrors
     */
    private function buildAudit(
        \PDO $staging,
        string $department,
        string $sourceFile,
        string $stagingFile,
        int $sourceLines,
        int $acceptedLines,
        int $rejectedLines,
        array $rejectionReasons,
        array $criticalErrors,
    ): DvfRepairAudit {
        $dates = $staging->query(
            'SELECT MIN(sale_date) min_date, MAX(sale_date) max_date FROM staged_sale'
        )->fetch(\PDO::FETCH_ASSOC);
        $yearCounts = array_fill_keys(range(self::EXPECTED_START_YEAR, self::EXPECTED_END_YEAR), 0);

        foreach ($staging->query(
            'SELECT CAST(substr(sale_date, 1, 4) AS INTEGER) year, COUNT(*) total
             FROM staged_sale GROUP BY substr(sale_date, 1, 4) ORDER BY year'
        ) as $row) {
            $year = (int) $row['year'];

            if (array_key_exists($year, $yearCounts)) {
                $yearCounts[$year] = (int) $row['total'];
            }
        }

        $missingYears = array_map(
            'intval',
            array_keys(array_filter($yearCounts, static fn(int $count): bool => $count === 0)),
        );
        $duplicateCounts = [];

        foreach (['idmutinvar', 'idmutation', 'idopendata'] as $identifier) {
            $duplicateCounts[$identifier] = (int) $staging->query(sprintf(
                'SELECT COALESCE(SUM(total - 1), 0) FROM (
                    SELECT COUNT(*) total FROM staged_sale
                    WHERE %1$s IS NOT NULL AND %1$s != ""
                    GROUP BY %1$s HAVING COUNT(*) > 1
                ) duplicates',
                $identifier,
            ))->fetchColumn();
        }
        $duplicateCounts['source_identity'] = (int) $staging->query(
            'SELECT COALESCE(SUM(total - 1), 0) FROM (
                SELECT COUNT(*) total FROM staged_sale
                WHERE idmutinvar != "" AND idmutation != "" AND idopendata != ""
                GROUP BY idmutinvar, idmutation, idopendata HAVING COUNT(*) > 1
            ) duplicates'
        )->fetchColumn();

        $vincennesPresent = false;
        $vincennesMaxDate = null;
        $recentVincennesApartments = 0;

        if ($department === '94') {
            $vincennes = $staging->query(
                'SELECT COUNT(*) total, MAX(sale_date) max_date
                 FROM staged_sale WHERE insee_code = "94080"'
            )->fetch(\PDO::FETCH_ASSOC);
            $vincennesPresent = (int) ($vincennes['total'] ?? 0) > 0;
            $vincennesMaxDate = $vincennes['max_date'] ?? null;
            $recentVincennesApartments = (int) $staging->query(
                'SELECT COUNT(*) FROM staged_sale
                 WHERE insee_code = "94080"
                   AND UPPER(property_type) = "UN APPARTEMENT"
                   AND surface BETWEEN 64 AND 96
                   AND sale_date >= "2025-01-01"
                   AND price_per_sqm > 1000
                   AND price_per_sqm < 15000'
            )->fetchColumn();

            if (!$vincennesPresent || $vincennesMaxDate === null || $vincennesMaxDate < '2025-01-01') {
                $criticalErrors[] = 'Le contrôle sentinelle de Vincennes (94080) a échoué.';
            }
        }

        return new DvfRepairAudit(
            department: $department,
            sourceFile: realpath($sourceFile) ?: $sourceFile,
            stagingFile: $stagingFile,
            sourceLines: $sourceLines,
            acceptedLines: $acceptedLines,
            rejectedLines: $rejectedLines,
            rejectionReasons: $rejectionReasons,
            minDate: $dates['min_date'] ?: null,
            maxDate: $dates['max_date'] ?: null,
            yearCounts: $yearCounts,
            missingYears: $missingYears,
            municipalityCount: (int) $staging->query('SELECT COUNT(DISTINCT insee_code) FROM staged_sale')->fetchColumn(),
            propertyTypeCount: (int) $staging->query('SELECT COUNT(DISTINCT property_type) FROM staged_sale')->fetchColumn(),
            duplicateCounts: $duplicateCounts,
            criticalErrors: array_values(array_unique($criticalErrors)),
            vincennesPresent: $vincennesPresent,
            vincennesMaxDate: $vincennesMaxDate,
            recentVincennesApartments: $recentVincennesApartments,
        );
    }

    /** @param array<string, int> $reasons */
    private function reject(array &$reasons, string $reason): void
    {
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    }

    /** @param list<string> $errors */
    private function addCriticalError(array &$errors, string $error): void
    {
        if (count($errors) < 20) {
            $errors[] = $error;
        }
    }

    private function parseNumber(string $value): float
    {
        return is_numeric(str_replace(',', '.', $value)) ? (float) str_replace(',', '.', $value) : 0.0;
    }

    private function parseNullableNumber(string $value): ?float
    {
        return $value === '' ? null : $this->parseNumber($value);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /** @return array{sql: string, params: list<string>} */
    private function departmentPredicate(string $department): array
    {
        return [
            'sql' => 'SUBSTRING(insee_code, 1, ?) = ?',
            'params' => [strlen($department), $department],
        ];
    }
}
