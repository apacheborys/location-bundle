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
class Coordinates implements Arrayable
{
    use ValidateCoordinatesTrait;

    /**
     * @var float
     */
    private $longitude;

    /**
     * @var float
     */
    private $latitude;

    public function __construct(float $longitude, float $latitude)
    {
        if (!self::assertLongitude($longitude)) {
            throw new \InvalidArgumentException('Wrong longitude argument');
        }
        if (!self::assertLatitude($latitude)) {
            throw new \InvalidArgumentException('Wrong latitude argument');
        }

        $this->longitude = $longitude;
        $this->latitude = $latitude;
    }

    /**
     * @return float
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @return float
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function toArray(): array
    {
        return [
            'lon' => $this->longitude,
            'lat' => $this->latitude,
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        foreach (['lon', 'lat'] as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new Coordinates((float)$raw['lon'], (float)$raw['lat']);
    }
}
