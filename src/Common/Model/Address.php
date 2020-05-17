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
class Address implements Arrayable
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var AdminLevelCollection
     */
    private $adminLevels;

    /**
     * @var string|int|null
     */
    private $streetNumber;

    /**
     * @var string|null
     */
    private $streetName;

    /**
     * @var string|null
     */
    private $subLocality;

    /**
     * @var string|null
     */
    private $locality;

    /**
     * @var Country|null
     */
    private $country;

    public function __construct(
        string $locale,
        AdminLevelCollection $adminLevels,
        string $streetNumber = null,
        string $streetName = null,
        string $locality = null,
        string $subLocality = null,
        Country $country = null
    ) {
        $this->locale = $locale;
        $this->adminLevels = $adminLevels;
        $this->streetNumber = $streetNumber;
        $this->streetName = $streetName;
        $this->locality = $locality;
        $this->subLocality = $subLocality;
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return AdminLevelCollection
     */
    public function getAdminLevels(): AdminLevelCollection
    {
        return $this->adminLevels;
    }

    /**
     * @return int|string|null
     */
    public function getStreetNumber()
    {
        return $this->streetNumber;
    }

    /**
     * @return string|null
     */
    public function getStreetName(): string
    {
        return $this->streetName;
    }

    /**
     * @return string|null
     */
    public function getSubLocality(): string
    {
        return $this->subLocality;
    }

    /**
     * @return string|null
     */
    public function getLocality(): string
    {
        return $this->locality;
    }

    /**
     * @return Country|null
     */
    public function getCountry(): Country
    {
        return $this->country;
    }

    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'adminLevels' => $this->adminLevels->toArray(),
            'streetNumber' => $this->streetNumber,
            'streetName' => $this->streetName,
            'locality' => $this->locality,
            'subLocality' => $this->subLocality,
            'country' => $this->country->toArray(),
        ];
    }

    public static function fromArray(array $raw): Arrayable
    {
        $properties = ['locale', 'adminLevels', 'streetNumber', 'streetName', 'locality', 'subLocality', 'country'];
        foreach ($properties as $property) {
            if (!isset($raw[$property])) {
                throw new \InvalidArgumentException(sprintf('Key %s not found in input array', $property));
            }
        }

        return new Address(
            $raw['locale'],
            AdminLevelCollection::fromArray($raw['adminLevels']),
            $raw['streetNumber'],
            $raw['streetName'],
            $raw['locality'],
            $raw['subLocality'],
            Country::fromArray($raw['country'])
        );
    }
}
