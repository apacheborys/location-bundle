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
use ApacheBorys\Location\Model\AdminLevel;
use ApacheBorys\Location\Model\AdminLevelCollection;
use ApacheBorys\Location\Model\Coordinates;
use ApacheBorys\Location\Database\DataBaseInterface;
use ApacheBorys\Location\Model\PairedCoordinates;
use ApacheBorys\Location\Model\PairedCoordinatesCollection;
use ApacheBorys\Location\Model\Place;
use ApacheBorys\Location\Model\PlaceCollection;
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

    public function geocodeQuery(GeocodeQuery $query): PlaceCollection
    {
        $places = $this->dataBase->get(
            $this->dataBase->normalizeStringForKeyName($query->getText()),
            0,
            $query->getLimit(),
            $query->getLocale() ? $query->getLocale() : ''
        );

        return new PlaceCollection($places ? $places : []);
    }

    public function reverseQuery(ReverseQuery $query): PlaceCollection
    {
        $result = $this->findPlaceByCoordinates($query->getCoordinates(), $query->getLocale() ? $query->getLocale() : '');

        return new PlaceCollection($result ? [$result] : []);
    }

    /**
     * Find places what boarded with specified Place. In default will be taken all places with same admin level.
     * Also you can specify own Places what you want to check. In maxDistanceToBorder you can define maximum distance
     * to possible neighbour point.
     * maxDistanceToBorder should be defined in kilometers
     *
     * @param Place   $originalPlace
     * @param float   $maxDistanceToBorder
     * @param Place[] $specificPlaces
     *
     * @return PairedCoordinatesCollection
     */
    public function findTouchedPlaces(
        Place $originalPlace,
        float $maxDistanceToBorder = 0.1,
        array $specificPlaces = []
    ): PairedCoordinatesCollection {
        $result = [];

        if (count($specificPlaces) > 0) {
            $touchedPlaces = $this->innerFindTouchedPlaces($originalPlace, $specificPlaces, $maxDistanceToBorder);
            if ($touchedPlaces->all()) {
                $result = array_merge($result, $touchedPlaces->all());
            }
        } else {
            $page = 0;

            while ($possiblePlaces = $this->dataBase->get(
                $this->dataBase->compileKey($originalPlace->getSelectedAddress(), true, true, false),
                $page,
                $this->dataBase->getDbConfig()->getMaxPlacesInOneResponse(),
                $originalPlace->getSelectedLocale(),
                $originalPlace->getMaxAdminLevel()
            )) {
                $touchedPlaces = $this->innerFindTouchedPlaces($originalPlace, $possiblePlaces, $maxDistanceToBorder);
                if ($touchedPlaces->all()) {
                    $result = array_merge($result, $touchedPlaces->all());
                }

                ++$page;
            }
        }

        return new PairedCoordinatesCollection($result);
    }

    /**
     * Measuring distance between two coordinates with take to attention altitude. Result in kilometers.
     *
     * @param Coordinates $a
     * @param Coordinates $b
     *
     * @return float
     */
    public static function distance(Coordinates $a, Coordinates $b): float
    {
        $delta_lat = $b->getLatitude() - $a->getLatitude();
        $delta_lon = $b->getLongitude() - $a->getLongitude();
        $delta_alt = abs($b->getAltitude() - $a->getAltitude()) * 0.001;

        $alpha = $delta_lat / 2;
        $beta = $delta_lon / 2;
        $arg =
            pow(sin(deg2rad($alpha)), 2) +
            cos(deg2rad($a->getLatitude())) * cos(deg2rad($b->getLatitude())) * pow(sin(deg2rad($beta)), 2);
        $arg_sin = asin(min(1, sqrt($arg)));
        $distance_flat = (2 * Constants::EARTH_RADIUS * $arg_sin);

        return sqrt(pow($distance_flat, 2) + pow($delta_alt, 2));
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
                $locale,
                $level
            )) {
                foreach ($possiblePlaces as $place) {
                    if ($result && $level < $place->getMaxAdminLevel()) {
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

    /**
     * @param Place   $originalPlace
     * @param Place[] $possiblePlaces
     * @param float   $maxDistanceToBorder
     *
     * @return PairedCoordinatesCollection
     */
    private function innerFindTouchedPlaces(
        Place $originalPlace,
        array $possiblePlaces,
        float $maxDistanceToBorder
    ): PairedCoordinatesCollection {
        $result = [];

        foreach ($possiblePlaces as $possiblePlace) {
            if ($originalPlace->isEqual($possiblePlace)) {
                continue;
            }

            $tempResult = $this->findTouchedCoord($originalPlace, $possiblePlace, $maxDistanceToBorder);
            if (count($tempResult) > 0) {
                $result = array_merge($result, $tempResult);
            }
        }

        return new PairedCoordinatesCollection($result);
    }

    /**
     * Returns paired coordinates what touch with two passed Places
     *
     * @param Place $originalPlace
     * @param Place $possiblePlace
     * @param float $maxDistanceToBorder
     *
     * @return PairedCoordinates[]
     */
    private function findTouchedCoord(Place $originalPlace, Place $possiblePlace, float $maxDistanceToBorder): array
    {
        $result = [];

        foreach ($possiblePlace->getPolygons() as $polygon) {
            foreach ($polygon->getCoordinates() as $coordinate) {
                $minDistance = $lastCoordinates = null;

                foreach ($originalPlace->getPolygons() as $origPolygon) {
                    foreach ($origPolygon->getCoordinates() as $origCoordinate) {
                        $distance = $this->distance($coordinate, $origCoordinate);

                        if ($distance <= $maxDistanceToBorder &&
                            (is_null($minDistance) || $minDistance > $distance)
                        ) {
                            $minDistance = $distance;
                            if (!is_null($lastCoordinates)) {
                                unset($lastCoordinates);
                            }
                            $lastCoordinates = new PairedCoordinates(
                                $origCoordinate,
                                $originalPlace,
                                $coordinate,
                                $possiblePlace,
                                $distance
                            );
                        }
                    }
                }

                if (!is_null($lastCoordinates)) {
                    $result[] = $lastCoordinates;
                }
            }
        }

        return $result;
    }

    public function getName(): string
    {
        return 'locationBundle';
    }
}
