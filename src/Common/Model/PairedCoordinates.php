<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Model;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class PairedCoordinates implements Arrayable
{
    /**
     * @var Coordinates
     */
    private $coordinatesA;

    /**
     * @var Place
     */
    private $placeA;

    /**
     * @var Coordinates;
     */
    private $coordinatesB;

    /**
     * @var Place
     */
    private $placeB;

    /**
     * @var float
     */
    private $distance;

    public function __construct(
        Coordinates $coordinatesA,
        Place $placeA,
        Coordinates $coordinatesB,
        Place $placeB,
        float $distance
    ) {
        $this->coordinatesA = $coordinatesA;
        $this->coordinatesB = $coordinatesB;
        $this->placeA = $placeA;
        $this->placeB = $placeB;
        $this->distance = $distance;
    }

    /**
     * @return Coordinates
     */
    public function getCoordinatesA(): Coordinates
    {
        return $this->coordinatesA;
    }

    /**
     * @return Place
     */
    public function getPlaceA(): Place
    {
        return $this->placeA;
    }

    /**
     * @return Coordinates
     */
    public function getCoordinatesB(): Coordinates
    {
        return $this->coordinatesB;
    }

    /**
     * @return Place
     */
    public function getPlaceB(): Place
    {
        return $this->placeB;
    }

    /**
     * @return float
     */
    public function getDistance(): float
    {
        return $this->distance;
    }

    public function toArray(): array
    {
        return [
            'coordinatesA' => $this->coordinatesA->toArray(),
            'coordinatesB' => $this->coordinatesB->toArray(),
            'placeA' => $this->placeA->toArray(),
            'placeB' => $this->placeB->toArray(),
            'distance' => $this->distance,
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        foreach (['coordinatesA', 'coordinatesB', 'placeA', 'placeB', 'distance'] as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new self(
            Coordinates::fromArray($raw['coordinatesA']),
            Place::createFromArray($raw['placeA']),
            Coordinates::fromArray($raw['coordinatesB']),
            Place::createFromArray($raw['placeB']),
            (float) $raw['distance']
        );
    }
}
