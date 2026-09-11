<?php

namespace App\Service;

use App\Dto\DvfExportResult;
use Doctrine\DBAL\Connection;

final class DvfNeonExporter
{
    public const COLUMNS = [
        'insee_code',
        'city',
        'property_type',
        'surface',
        'price',
        'price_per_sqm',
        'sale_date',
        'x',
        'y',
        'source',
    ];

    public function __construct(private readonly Connection $connection) {}

    /** @param list<string> $departments */
    public function export(array $departments, string $outputPath): DvfExportResult
    {
        $departments = $this->normalizeDepartments($departments);
        $outputPath = trim($outputPath);

        if ($outputPath === '') {
            throw new \InvalidArgumentException('Le chemin du fichier de sortie est obligatoire.');
        }

        $directory = dirname($outputPath);

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \InvalidArgumentException(sprintf('Le dossier de sortie est introuvable ou non accessible en écriture : %s', $directory));
        }

        $handle = fopen($outputPath, 'xb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Impossible de créer le fichier (il existe peut-être déjà) : %s', $outputPath));
        }

        $completed = false;

        try {
            $this->writeCsvRow($handle, self::COLUMNS);

            [$where, $parameters] = $this->departmentFilter($departments);
            $departmentExpression = $this->departmentExpression();
            $sql = sprintf(
                'SELECT %s FROM comparable_sale
                 WHERE %s IN (%s)
                 ORDER BY %s ASC, sale_date ASC, insee_code ASC, id ASC',
                implode(', ', self::COLUMNS),
                $departmentExpression,
                $where,
                $departmentExpression,
            );
            $result = $this->connection->executeQuery($sql, $parameters);
            $counts = array_fill_keys($departments, 0);
            $total = 0;

            foreach ($result->iterateAssociative() as $row) {
                $department = self::departmentFromInseeCode((string) $row['insee_code']);

                if (!array_key_exists($department, $counts)) {
                    throw new \RuntimeException(sprintf('Une ligne hors périmètre a été retournée : %s', $row['insee_code']));
                }

                $values = [];

                foreach (self::COLUMNS as $column) {
                    $value = $row[$column] ?? null;

                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        throw new \RuntimeException(sprintf('Valeur non UTF-8 détectée dans la colonne %s.', $column));
                    }

                    $values[] = $value;
                }

                $this->writeCsvRow($handle, $values);
                ++$counts[$department];
                ++$total;
            }

            if (!fflush($handle)) {
                throw new \RuntimeException('Impossible de finaliser le fichier CSV.');
            }

            $completed = true;
        } finally {
            fclose($handle);

            if (!$completed && is_file($outputPath)) {
                @unlink($outputPath);
            }
        }

        clearstatcache(true, $outputPath);

        return new DvfExportResult(
            outputPath: realpath($outputPath) ?: $outputPath,
            totalRows: $total,
            countsByDepartment: $counts,
            fileSize: filesize($outputPath) ?: 0,
        );
    }

    /** @param list<string> $departments
     *  @return list<string>
     */
    private function normalizeDepartments(array $departments): array
    {
        if ($departments === []) {
            throw new \InvalidArgumentException('Au moins un département doit être indiqué.');
        }

        $normalized = array_map(
            static fn(string $department): string => DvfDepartmentRepairer::normalizeDepartment($department),
            $departments,
        );
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /** @param list<string> $departments
     *  @return array{string, array<string, string>}
     */
    private function departmentFilter(array $departments): array
    {
        $placeholders = [];
        $parameters = [];

        foreach ($departments as $index => $department) {
            $name = 'department_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $department;
        }

        return [implode(', ', $placeholders), $parameters];
    }

    private function departmentExpression(): string
    {
        return "CASE
            WHEN SUBSTRING(UPPER(TRIM(insee_code)), 1, 3) IN ('971', '972', '973', '974', '976')
                THEN SUBSTRING(UPPER(TRIM(insee_code)), 1, 3)
            WHEN SUBSTRING(UPPER(TRIM(insee_code)), 1, 2) IN ('2A', '2B')
                THEN SUBSTRING(UPPER(TRIM(insee_code)), 1, 2)
            ELSE SUBSTRING(UPPER(TRIM(insee_code)), 1, 2)
        END";
    }

    private static function departmentFromInseeCode(string $inseeCode): string
    {
        $inseeCode = strtoupper(trim($inseeCode));
        $threeCharacters = substr($inseeCode, 0, 3);

        if (in_array($threeCharacters, ['971', '972', '973', '974', '976'], true)) {
            return $threeCharacters;
        }

        return substr($inseeCode, 0, 2);
    }

    /** @param resource $handle
     *  @param array<int, mixed> $values
     */
    private function writeCsvRow($handle, array $values): void
    {
        if (fputcsv($handle, $values, ',', '"', '') === false) {
            throw new \RuntimeException('Erreur pendant l’écriture du fichier CSV.');
        }
    }
}
