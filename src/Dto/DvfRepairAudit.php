<?php

namespace App\Dto;

final readonly class DvfRepairAudit
{
    /**
     * @param array<string, int> $rejectionReasons
     * @param array<int, int>    $yearCounts
     * @param list<int>          $missingYears
     * @param array<string, int> $duplicateCounts
     * @param list<string>       $criticalErrors
     */
    public function __construct(
        public string $department,
        public string $sourceFile,
        public string $stagingFile,
        public int $sourceLines,
        public int $acceptedLines,
        public int $rejectedLines,
        public array $rejectionReasons,
        public ?string $minDate,
        public ?string $maxDate,
        public array $yearCounts,
        public array $missingYears,
        public int $municipalityCount,
        public int $propertyTypeCount,
        public array $duplicateCounts,
        public array $criticalErrors,
        public bool $vincennesPresent,
        public ?string $vincennesMaxDate,
        public int $recentVincennesApartments,
    ) {}

    public function isValid(): bool
    {
        return $this->sourceLines > 0
            && $this->acceptedLines > 0
            && $this->sourceLines === $this->acceptedLines + $this->rejectedLines
            && $this->criticalErrors === []
            && ($this->duplicateCounts['source_identity'] ?? 0) === 0
            && $this->missingYears === []
            && $this->maxDate !== null
            && $this->maxDate >= '2025-01-01';
    }
}
