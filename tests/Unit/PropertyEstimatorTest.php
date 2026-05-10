<?php

namespace App\Tests\Unit;

use App\Service\PropertyEstimator;
use PHPUnit\Framework\TestCase;

class PropertyEstimatorTest extends TestCase
{
    private function createEstimator(): PropertyEstimator
    {
        return new PropertyEstimator();
    }

    public function testEstimateBasicProperty(): void
    {
        $estimator = $this->createEstimator();

        $result = $estimator->estimate([
            'type' => 'Appartement',
            'city' => 'Paris',
            'postalCode' => '75001',
            'surface' => 50,
            'rooms' => 2,
            'parking' => false,
        ]);

        $this->assertIsArray($result);
        $this->assertNotNull($result['estimate']);
        $this->assertNotNull($result['lowEstimate']);
        $this->assertNotNull($result['highEstimate']);
        $this->assertNull($result['adText']);

        $this->assertGreaterThan(0, $result['estimate']);
        $this->assertLessThan($result['estimate'], $result['lowEstimate']);
        $this->assertGreaterThan($result['estimate'], $result['highEstimate']);
    }

    public function testEstimateWithParkingAddsValue(): void
    {
        $estimator = $this->createEstimator();

        $base = $estimator->estimate([
            'type' => 'Maison',
            'city' => 'Lyon',
            'postalCode' => '69000',
            'surface' => 100,
            'rooms' => 4,
            'parking' => false,
        ]);

        $withParking = $estimator->estimate([
            'type' => 'Maison',
            'city' => 'Lyon',
            'postalCode' => '69000',
            'surface' => 100,
            'rooms' => 4,
            'parking' => true,
        ]);

        $this->assertGreaterThan($base['estimate'], $withParking['estimate']);
    }

    public function testParkingOnlyProperty(): void
    {
        $estimator = $this->createEstimator();

        $result = $estimator->estimate([
            'type' => 'Parking',
            'city' => 'Paris',
            'postalCode' => '75001',
            'surface' => 12,
            'rooms' => 0,
            'parking' => true,
        ]);

        $this->assertNotNull($result['estimate']);
        $this->assertGreaterThan(0, $result['estimate']);
        $this->assertNull($result['adText']);
    }

    public function testInvalidDataReturnsNullEstimate(): void
    {
        $estimator = $this->createEstimator();

        $result = $estimator->estimate([
            'type' => '',
            'city' => '',
            'postalCode' => '123',
            'surface' => 0,
            'rooms' => 0,
            'parking' => false,
        ]);

        $this->assertNull($result['estimate']);
        $this->assertNull($result['lowEstimate']);
        $this->assertNull($result['highEstimate']);
        $this->assertNull($result['adText']);
    }
}