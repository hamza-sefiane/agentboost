<?php

namespace App\Repository;

use App\Entity\ComparableSale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ComparableSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComparableSale::class);
    }

    /**
     * @return ComparableSale[]
     */
    public function findSalesForValuation(
        string $inseeCode,
        string $propertyType,
        int $surface,
        int $limit = 10,
    ): array {
        $attempts = [
            ['minDate' => '2021-01-01', 'surfaceRatio' => 0.2],
            ['minDate' => '2017-01-01', 'surfaceRatio' => 0.2],
            ['minDate' => '2017-01-01', 'surfaceRatio' => 0.3],
        ];

        foreach ($attempts as $attempt) {
            $results = $this->searchSalesForValuation(
                $inseeCode,
                $propertyType,
                $surface,
                $attempt['surfaceRatio'],
                new \DateTime($attempt['minDate']),
                $limit
            );

            if (count($results) >= min(3, $limit)) {
                return $results;
            }
        }

        return $results ?? [];
    }

    /**
     * @return ComparableSale[]
     */
    public function findBestComparables(
        string $inseeCode,
        string $propertyType,
        int $surface,
        int $limit = 3,
    ): array {
        return array_slice(
            $this->findSalesForValuation($inseeCode, $propertyType, $surface, max(10, $limit)),
            0,
            $limit
        );
    }

    /**
     * @return ComparableSale[]
     */
    private function searchSalesForValuation(
        string $inseeCode,
        string $propertyType,
        int $surface,
        float $surfaceRatio,
        \DateTimeInterface $minDate,
        int $limit,
    ): array {
        $normalizedType = match (mb_strtolower($propertyType)) {
            'maison' => 'UNE MAISON',
            'appartement' => 'UN APPARTEMENT',
            default => mb_strtoupper($propertyType),
        };

        $minSurface = (int) floor($surface * (1 - $surfaceRatio));
        $maxSurface = (int) ceil($surface * (1 + $surfaceRatio));

        return $this->createQueryBuilder('c')
            ->andWhere('c.inseeCode = :inseeCode')
            ->andWhere('c.propertyType = :propertyType')
            ->andWhere('c.surface BETWEEN :minSurface AND :maxSurface')
            ->andWhere('c.saleDate >= :minDate')
            ->andWhere('c.pricePerSqm > 1000')
            ->andWhere('c.pricePerSqm < 15000')
            ->setParameter('inseeCode', $inseeCode)
            ->setParameter('propertyType', $normalizedType)
            ->setParameter('minSurface', $minSurface)
            ->setParameter('maxSurface', $maxSurface)
            ->setParameter('minDate', $minDate)
            ->orderBy('c.saleDate', 'DESC')
            ->addOrderBy('c.surface', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
