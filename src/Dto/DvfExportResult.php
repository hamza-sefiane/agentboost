<?php

namespace App\Dto;

final readonly class DvfExportResult
{
    /** @param array<string, int> $countsByDepartment */
    public function __construct(
        public string $outputPath,
        public int $totalRows,
        public array $countsByDepartment,
        public int $fileSize,
    ) {}
}
