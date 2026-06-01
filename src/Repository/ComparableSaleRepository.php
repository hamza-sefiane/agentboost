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
        $normalizedType = match (mb_strtolower($propertyType)) {
            'maison' => 'UNE MAISON',
            'appartement' => 'UN APPARTEMENT',
            default => mb_strtoupper($propertyType),
        };

        $minSurface = (int) floor($surface * 0.8);
        $maxSurface = (int) ceil($surface * 1.2);

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
            ->setParameter('minDate', new \DateTime('2021-01-01'))
            ->orderBy('c.saleDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
        $attempts = [
            ['minDate' => '2023-01-01', 'surfaceRatio' => 0.2],
            ['minDate' => '2021-01-01', 'surfaceRatio' => 0.2],
            ['minDate' => '2021-01-01', 'surfaceRatio' => 0.3],
        ];

        foreach ($attempts as $attempt) {
            $results = $this->searchComparables(
                $inseeCode,
                $propertyType,
                $surface,
                $attempt['surfaceRatio'],
                new \DateTime($attempt['minDate']),
                $limit
            );

            if (count($results) >= $limit) {
                return $results;
            }
        }

        return $results ?? [];
    }

    /**
     * @return ComparableSale[]
     */
    private function searchComparables(
        string $inseeCode,
        string $propertyType,
        int $surface,
        float $surfaceRatio,
        \DateTimeInterface $minDate,
        int $limit,
    ): array {
        $minSurface = (int) floor($surface * (1 - $surfaceRatio));
        $maxSurface = (int) ceil($surface * (1 + $surfaceRatio));

        return $this->createQueryBuilder('c')
            ->andWhere('c.inseeCode = :inseeCode')
            ->andWhere('c.propertyType LIKE :propertyType')
            ->andWhere('c.surface BETWEEN :minSurface AND :maxSurface')
            ->andWhere('c.saleDate >= :minDate')
            ->andWhere('c.price > 50000')
            ->setParameter('inseeCode', $inseeCode)
            ->setParameter('propertyType', '%' . mb_strtoupper($propertyType) . '%')
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
