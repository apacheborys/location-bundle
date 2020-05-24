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

use ApacheBorys\Location\Traits\ValidateCoordinatesTrait;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class Bounds implements Arrayable
{
    use ValidateCoordinatesTrait;

    /**
     * @var float
     */
    private $west;

    /**
     * @var float
     */
    private $east;

    /**
     * @var float
     */
    private $north;

    /**
     * @var float
     */
    private $south;

    public function __construct(float $west, float $east, float $north, float $south)
    {
        if (!self::assertLongitude($west)) {
            throw new \InvalidArgumentException('Wrong value for west');
        }
        if (!self::assertLongitude($east)) {
            throw new \InvalidArgumentException('Wrong value for east');
        }
        if (!self::assertLatitude($north)) {
            throw new \InvalidArgumentException('Wrong value for north');
        }
        if (!self::assertLatitude($south)) {
            throw new \InvalidArgumentException('Wrong value for south');
        }

        $this->west = $west;
        $this->east = $east;
        $this->north = $north;
        $this->south = $south;
    }

    /**
     * @return float
     */
    public function getWest(): float
    {
        return $this->west;
    }

    /**
     * @return float
     */
    public function getEast(): float
    {
        return $this->east;
    }

    /**
     * @return float
     */
    public function getNorth(): float
    {
        return $this->north;
    }

    /**
     * @return float
     */
    public function getSouth(): float
    {
        return $this->south;
    }

    public function toArray(): array
    {
        return [
            'west' => $this->west,
            'east' => $this->east,
            'north' => $this->north,
            'south' => $this->south,
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        foreach (['west', 'east', 'north', 'south'] as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new self((float) $raw['west'], (float) $raw['east'], (float) $raw['north'], (float) $raw['south']);
    }
}
