<?php

namespace App\Repository;

use App\Entity\CityReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CityReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CityReference::class);
    }

    public function findInseeCode(string $city, string $postalCode): ?string
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.inseeCode')
            ->andWhere('c.postalCode = :postalCode')
            ->andWhere('c.normalizedCity = :normalizedCity')
            ->setParameter('postalCode', trim($postalCode))
            ->setParameter('normalizedCity', $this->normalizeCity($city))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['inseeCode'] ?? null;
    }

    private function normalizeCity(string $city): string
    {
        $city = mb_strtolower(trim($city));

        $city = str_replace(
            ['à', 'â', 'ä', 'ç', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ÿ'],
            ['a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y'],
            $city
        );

        return preg_replace('/[^a-z0-9]+/', '-', $city) ?: $city;
    }
}
