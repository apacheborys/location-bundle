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
class Place
{
    const DEFAULT_LOCALE = 'en';

    /**
     * @var string|null
     */
    private $postalCode;

    /**
     * @var string|null
     */
    private $timezone;

    /**
     * @var string
     */
    private $providedBy;

    /**
     * @var Bounds|null
     */
    private $bounds;

    /**
     * @var Polygon[]|null
     */
    private $polygons;

    /**
     * @var Address[]
     */
    private $addresses = [];

    /**
     * Current selected locale
     *
     * @var string
     */
    private $currentLocale;

    /**
     * Unique id of Place object
     *
     * @var string
     */
    private $objectHash;

    /**
     * @param Address|Address[] $address
     * @param Polygon[]|null    $polygons
     * @param string            $locale
     * @param string|null       $postalCode
     * @param string|null       $timezone
     * @param string            $providedBy
     * @param Bounds            $bounds
     */
    public function __construct(
        $address,
        array $polygons = null,
        string $locale = self::DEFAULT_LOCALE,
        string $postalCode = null,
        string $timezone = null,
        string $providedBy = '',
        Bounds $bounds = null
    ) {
        if (is_array($address)) {
            foreach ($address as $localeNode => $addressNode) {
                $this->addresses[$localeNode] = $addressNode;
            }
        } else {
            $this->addresses[$locale] = $address;
        }

        $this->polygons = $polygons;
        $this->currentLocale = $locale;
        $this->postalCode = $postalCode;
        $this->timezone = $timezone;
        $this->providedBy = $providedBy;
        $this->bounds = $bounds;
    }

    /**
     * Return Address object in selected locale
     *
     * @return Address
     */
    public function getSelectedAddress(): Address
    {
        return $this->addresses[$this->currentLocale];
    }

    /**
     * Set Address for selected locale
     *
     * @param Address $address
     *
     * @return bool
     */
    public function setSelectedAddress(Address $address): bool
    {
        $this->addresses[$this->currentLocale] = $address;

        return true;
    }

    /**
     * @param string $locale
     *
     * @return bool
     */
    public function selectLocale(string $locale): bool
    {
        $this->currentLocale = $locale;

        return true;
    }

    public function getSelectedLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Return associated array with available Address object and locales as keys
     *
     * @return Address[]
     */
    public function getAvailableAddresses(): array
    {
        return $this->addresses;
    }

    /**
     * Returning maximum admin level for entity
     *
     * @return int
     */
    public function getMaxAdminLevel(): int
    {
        $address = $this->getSelectedAddress();

        $max = 0;
        /** @var AdminLevel $level */
        foreach ($address->getAdminLevels() as $level) {
            if ($level->getLevel() > $max) {
                $max = $level->getLevel();
            }
        }

        return $max;
    }

    /**
     * @param Polygon[] $polygons
     *
     * @return Place
     */
    public function setPolygons(array $polygons)
    {
        $this->polygons = $polygons;

        return $this;
    }

    /**
     * @param array $rawPolygons
     *
     * @return $this
     */
    public function setPolygonsFromArray(array $rawPolygons): self
    {
        foreach ($rawPolygons as $rawPolygon) {
            $tempPolygon = new Polygon();
            foreach ($rawPolygon as $coordinate) {
                $tempPolygon->addCoordinates(new Coordinates($coordinate['lon'], $coordinate['lat']));
            }
            $this->polygons[] = $tempPolygon;
        }

        return $this;
    }

    /**
     * @return Polygon[]
     */
    public function getPolygons()
    {
        return $this->polygons;
    }

    public function getPolygonsAsArray()
    {
        $result = [];
        if (is_array($this->polygons)) {
            foreach ($this->polygons as $key => $polygon) {
                $result[$key] = $polygon->toArray();
            }
        }

        return $result;
    }

    /**
     * @param array $data
     * @param array $includeLocales
     *
     * @return Place
     */
    public static function createFromArray(array $data, array $includeLocales = []): Place
    {
        $addresses = [];
        $firstLocale = '';
        if (isset($data['address']) && is_array($data['address'])) {
            count($includeLocales) > 0
                ? $preparedData = array_intersect_key($data['address'], array_fill_keys($includeLocales, true))
                : $preparedData = $data['address'];

            foreach ($preparedData as $locale => $rawAddress) {
                if ('' === $firstLocale) {
                    $firstLocale = $locale;
                }

                $addresses[$locale] = Address::fromArray($rawAddress);
            }
        }

        $place = new self(
            $addresses,
            null,
            $firstLocale,
            $data['postalCode'],
            $data['timezone'],
            $data['providedBy'],
            Bounds::fromArray($data['bounds'])
        );

        if (isset($data['polygons'])) {
            $place->setPolygonsFromArray($data['polygons']);
        }

        if (isset($data['hash'])) {
            $place->setObjectHash($data['hash']);
        }

        return $place;
    }

    /**
     * @return string
     */
    public function getObjectHash(): string
    {
        return $this->objectHash;
    }

    /**
     * @param string $objectHash
     *
     * @return Place
     */
    public function setObjectHash(string $objectHash): self
    {
        $this->objectHash = $objectHash;

        return $this;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->addresses as $locale => $address) {
            $result['address'][$locale] = $address->toArray();
        }

        $result['polygons'] = $this->getPolygonsAsArray();
        $result['hash'] = $this->objectHash;
        $result['postalCode'] = $this->postalCode;
        $result['providedBy'] = $this->providedBy;
        $result['bounds'] = $this->bounds->toArray();
        $result['timezone'] = $this->timezone;

        return $result;
    }

    /**
     * @return string|null
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * @param string|null $postalCode
     */
    public function setPostalCode(string $postalCode)
    {
        $this->postalCode = $postalCode;
    }

    /**
     * @return string|null
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * @param string|null $timezone
     */
    public function setTimezone(string $timezone)
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getProvidedBy(): string
    {
        return $this->providedBy;
    }

    /**
     * @param string $providedBy
     */
    public function setProvidedBy(string $providedBy)
    {
        $this->providedBy = $providedBy;
    }
}
