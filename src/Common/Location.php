<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location;

use ApacheBorys\Location\Model\Address;
use ApacheBorys\Location\Model\AddressCollection;
use ApacheBorys\Location\Model\AdminLevel;
use ApacheBorys\Location\Model\AdminLevelCollection;
use ApacheBorys\Location\Model\Coordinates;
use ApacheBorys\Location\Database\DataBaseInterface;
use ApacheBorys\Location\Model\Place;
use ApacheBorys\Location\Model\Polygon;
use ApacheBorys\Location\Query\GeocodeQuery;
use ApacheBorys\Location\Query\ReverseQuery;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class Location
{
    /**
     * @var DataBaseInterface
     */
    private $dataBase;

    public function __construct(DataBaseInterface $dataBase)
    {
        $this->dataBase = $dataBase;
    }

    public function addPlace(Place $place): bool
    {
        return $this->dataBase->add($place);
    }

    public function deletePlace(Place $place): bool
    {
        return $this->dataBase->delete($place);
    }

    /**
     * @param int $offset
     * @param int $limit
     *
     * @return Place[]
     */
    public function getAllPlaces(int $offset = 0, int $limit = 50): array
    {
        return $this->dataBase->getAllPlaces($offset, $limit);
    }

    public function geocodeQuery(GeocodeQuery $query): AddressCollection
    {
        $result = [];
        $places = $this->dataBase->get(
            $this->dataBase->normalizeStringForKeyName($query->getText()),
            0,
            $query->getLimit(),
            $query->getLocale() ? $query->getLocale() : ''
        );

        foreach ($places as $place) {
            $result = array_merge($result, $place->getAvailableAddresses());
        }

        return new AddressCollection($result);
    }

    public function reverseQuery(ReverseQuery $query): AddressCollection
    {
        $result = $this->findPlaceByCoordinates($query->getCoordinates(), $query->getLocale() ? $query->getLocale() : '');

        return new AddressCollection($result ? $result->getAvailableAddresses() : []);
    }

    /**
     * @param Coordinates $coordinates
     * @param string      $locale
     *
     * @return Place|null
     */
    private function findPlaceByCoordinates(Coordinates $coordinates, string $locale = '')
    {
        $levels = $this->dataBase->getAdminLevels();
        asort($levels);

        /** @var Place|null $result */
        $result = null;
        foreach ($levels as $level) {
            $result ?
                $tempPlace = $result->getSelectedAddress() :
                $tempPlace = new Address($this->getName(), new AdminLevelCollection([new AdminLevel($level, ',')]));

            $page = 0;
            while ($possiblePlaces = $this->dataBase->get(
                $this->dataBase->compileKey($tempPlace, true, true, false),
                $page,
                $this->dataBase->getDbConfig()->getMaxPlacesInOneResponse(),
                $locale
            )) {
                foreach ($possiblePlaces as $place) {
                    if ($result && $level <= $place->getMaxAdminLevel()) {
                        continue;
                    }

                    foreach ($place->getPolygons() as $polygon) {
                        if ($this->checkCoordInBundle($coordinates->getLatitude(), $coordinates->getLongitude(), $polygon)) {
                            $result = $place;

                            break 3;
                        }
                    }
                }

                ++$page;
            }

            break;
        }

        return $result;
    }

    /**
     * Check bundle for coordinates
     *
     * @param float   $latitude
     * @param float   $longitude
     * @param Polygon $polygon
     *
     * @return bool|int
     */
    private function checkCoordInBundle(float $latitude, float $longitude, Polygon $polygon)
    {
        $vertices_x = [];
        $vertices_y = [];

        foreach ($polygon->getCoordinates() as $coordinate) {
            $vertices_x[] = $coordinate->getLongitude();
            $vertices_y[] = $coordinate->getLatitude();
        }

        $points_polygon = count($vertices_x) - 1;

        return $this->isInPolygon($points_polygon, $vertices_x, $vertices_y, $longitude, $latitude);
    }

    /**
     * Check polygon for intersecting specific coordinates
     *
     * @param int     $points_polygon
     * @param float[] $vertices_x
     * @param float[] $vertices_y
     * @param float   $longitude_x
     * @param float   $latitude_y
     *
     * @return bool
     */
    private function isInPolygon(
        int $points_polygon,
        array $vertices_x,
        array $vertices_y,
        float $longitude_x,
        float $latitude_y
    ) {
        $c = 0;
        for ($i = 0, $j = $points_polygon; $i < $points_polygon; $j = $i++) {
            if (($vertices_y[$i] > $latitude_y != ($vertices_y[$j] > $latitude_y)) &&
                ($longitude_x < ($vertices_x[$j] - $vertices_x[$i]) *
                    ($latitude_y - $vertices_y[$i]) / ($vertices_y[$j] - $vertices_y[$i]) + $vertices_x[$i])
            ) {
                $c = !$c;
            }
        }

        return (bool) $c;
    }

    public function getName(): string
    {
        return 'locationBundle';
    }
}
