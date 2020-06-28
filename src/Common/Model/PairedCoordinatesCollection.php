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

use ApacheBorys\Location\Location;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
final class PairedCoordinatesCollection implements \IteratorAggregate, \Countable, Arrayable
{
    /**
     * @var PairedCoordinates[]
     */
    private $pairedCoordinates;

    /**
     * PairedCoordinatesCollection constructor.
     *
     * @param PairedCoordinates[] $pairedCoordinates
     */
    public function __construct(array $pairedCoordinates = [])
    {
        $this->validateBeforeAdd($pairedCoordinates);
        $this->pairedCoordinates = $pairedCoordinates;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->pairedCoordinates as $pairedCoordinates) {
            $result[] = $pairedCoordinates->toArray();
        }

        return $result;
    }

    public static function fromArray(array $raw): Arrayable
    {
        $result = [];

        foreach ($raw as $rawPairCoordinates) {
            $result[] = PairedCoordinates::fromArray($rawPairCoordinates);
        }

        return new self($result);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    public function count()
    {
        return count($this->pairedCoordinates);
    }

    /**
     * @param PairedCoordinates[] $addresses
     */
    private function validateBeforeAdd(array $addresses)
    {
        foreach ($addresses as $address) {
            if (!($address instanceof PairedCoordinates)) {
                throw new \InvalidArgumentException(sprintf('Invalid instance value type. Expected: %s, in fact: %s', PairedCoordinates::class, get_class($address)));
            }
        }
    }

    /**
     * @return AdminLevel[]
     */
    public function all(): array
    {
        return $this->pairedCoordinates;
    }

    /**
     * Filter all pair coordinates relates to specific place
     *
     * @param Place $place
     *
     * @return PairedCoordinates[]
     */
    public function filterByPlace(Place $place): array
    {
        return array_filter(
            $this->pairedCoordinates,
            function ($pairedCoordinates) use ($place) {
                return $pairedCoordinates->getPlaceA()->getObjectHash() === $place->getObjectHash() ||
                    $pairedCoordinates->getPlaceB()->getObjectHash() === $place->getObjectHash();
            }
        );
    }

    /**
     * Sort pairs of coordinates in collection according to location of specific coordinates. Also you can specify
     * place what you want to exclude.
     *
     * @param Coordinates $coordinates
     * @param Place|null  $excludePlace
     *
     * @return PairedCoordinates[]
     */
    public function sortNearestPair(Coordinates $coordinates, $excludePlace = null): array
    {
        $result = [];

        foreach ($this->pairedCoordinates as $pairedCoordinates) {
            $distanceA = $distanceB = null;

            if ($excludePlace && $excludePlace instanceof Place) {
                if ($excludePlace->getObjectHash() === $pairedCoordinates->getPlaceA()->getObjectHash()) {
                    $distanceB = Location::distance($pairedCoordinates->getCoordinatesB(), $coordinates);
                } else {
                    $distanceA = Location::distance($pairedCoordinates->getCoordinatesA(), $coordinates);
                }
            } else {
                $distanceA = Location::distance($pairedCoordinates->getCoordinatesA(), $coordinates);
                $distanceB = Location::distance($pairedCoordinates->getCoordinatesB(), $coordinates);
            }

            $result[min($distanceA, $distanceB)][] = $pairedCoordinates;
        }
        ksort($result);

        $finalResult = [];
        foreach ($result as $pairCollection) {
            $finalResult = array_merge($finalResult, $pairCollection);
        }

        return $result;
    }
}
