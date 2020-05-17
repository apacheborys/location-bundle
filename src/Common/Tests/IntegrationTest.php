<?php

declare(strict_types=1);

/*
 * This file is part of the Location bundle package (@see https://github.com/apacheborys/location-bundle).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace ApacheBorys\Location\Tests;

use ApacheBorys\Location\Model\Address;
use ApacheBorys\Location\Model\AdminLevel;
use ApacheBorys\Location\Model\AdminLevelCollection;
use ApacheBorys\Location\Model\Bounds;
use ApacheBorys\Location\Model\Coordinates;
use ApacheBorys\Location\Model\Country;
use ApacheBorys\Location\Database\Psr6Database;
use ApacheBorys\Location\Model\DBConfig;
use ApacheBorys\Location\Model\Place;
use ApacheBorys\Location\Model\Polygon;
use ApacheBorys\Location\Location;
use ApacheBorys\Location\Query\GeocodeQuery;
use ApacheBorys\Location\Query\ReverseQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * @author Borys Yermokhin <borys_ermokhin@yahoo.com>
 */
class IntegrationTest extends TestCase
{
    const ELEM_LATITUDE = 'latitude';

    const ELEM_LONGITUDE = 'longitude';

    const ELEM_EXPECTED = 'expected';

    const ELEM_STREET_NUMBER = 'streetNumber';

    const ELEM_STREET_NAME = 'streetName';

    const ELEM_SUB_LOCALITY = 'subLocality';

    const ELEM_LOCALITY = 'locality';

    const ELEM_POSTAL_CODE = 'postalCode';

    private $countCoordFiles = 0;

    /** @var Location */
    private $location;

    public function __construct()
    {
        parent::__construct();

        $dataBase = new Psr6Database(new FilesystemAdapter(), new DBConfig());
        $this->location = new Location($dataBase);
        $this->loadJsonCoordinates($this->location);
    }

    /**
     * Test fetch address from nested polygons
     *
     * @dataProvider providerNestedPolygons
     *
     * @param float $lat
     * @param float $lon
     * @param array $expected
     */
    public function testNestedPolygons(float $lat, float $lon, array $expected)
    {
        $result = $this->location->reverseQuery(new ReverseQuery(new Coordinates($lon, $lat), 0, 'en'));
        /** @var Address $address */
        $address = $result->getIterator()->first();

        $this->assertEquals($expected[self::ELEM_STREET_NUMBER], $address->getStreetNumber());
        $this->assertEquals($expected[self::ELEM_STREET_NUMBER], $address->getStreetNumber());
        $this->assertEquals($expected[self::ELEM_SUB_LOCALITY], $address->getSubLocality());
        $this->assertEquals($expected[self::ELEM_LOCALITY], $address->getLocality());
    }

    /**
     * @covers \ApacheBorys\Location\Location::getAllPlaces
     */
    public function testGetAllPlaces()
    {
        $totalCount = 0;
        $page = 0;
        while ($places = $this->location->getAllPlaces($page * 50)) {
            foreach ($places as $place) {
                $this->assertEquals(Place::class, get_class($place));
                ++$totalCount;
            }
            ++$page;
        }
        $this->assertEquals($this->countCoordFiles, $totalCount);
    }

    /**
     * @covers \ApacheBorys\Location\Location::deletePlace
     */
    public function testDeletePlace()
    {
        $places = \SplFixedArray::fromArray($this->location->getAllPlaces());
        $places->rewind();
        $this->location->deletePlace($places->current());

        $totalCount = 0;
        $page = 0;
        while ($places = $this->location->getAllPlaces($page * 50)) {
            $totalCount += count($places);
            ++$page;
        }
        $this->assertEquals($this->countCoordFiles - 1, $totalCount);
    }

    /**
     * Additional geocodeQuery with specific locale
     */
    public function testGeocodeQueryWithLocale()
    {
        $query = new GeocodeQuery('Oberkassel, Düsseldorf', 0, 'de');
        $result = $this->location->geocodeQuery($query);

        // Check Dusseldorf assets in german language
        $this->checkDusseldorfAssetsInGermanLang($result->getIterator()->first());
    }

    public function testReverseQueryWithLocale()
    {
        // Close to the white house
        $result = $this->location->reverseQuery(new ReverseQuery(new Coordinates(51.231426, 6.761729),0, 'de'));

        // Check Dusseldorf assets in german language
        $this->checkDusseldorfAssetsInGermanLang($result->first());
    }

    /**
     * @see testNestedPolygons
     * @case 1 Should return first, main layer
     * @case 2 Should return third, last layer
     * @case 3 Should return second layer, with elevated precise of coordinates
     *
     * @return iterable
     */
    public function providerNestedPolygons(): \Iterator
    {
        /* Altstadt, should be return first layer of coordinates, total Dusseldorf's Place */
        yield [
            self::ELEM_LATITUDE => 51.227546,
            self::ELEM_LONGITUDE => 6.784593,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '',
                self::ELEM_STREET_NAME => '',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '',
            ],
        ];

        /* BestenPlatz, should be return third layer what nested inside other two layers */
        yield [
            self::ELEM_LATITUDE => 51.2314767,
            self::ELEM_LONGITUDE => 6.7473107,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '1',
                self::ELEM_STREET_NAME => 'Belsenplatz',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '40545',
            ],
        ];

        /* LuegPlatz, should be return second layer what nested inside first layer */
        /* Additionally testing with elevated precise for coordinates */
        yield [
            self::ELEM_LATITUDE => 51.2314260099,
            self::ELEM_LONGITUDE => 6.7617290099,
            self::ELEM_EXPECTED => [
                self::ELEM_STREET_NUMBER => '',
                self::ELEM_STREET_NAME => '',
                self::ELEM_SUB_LOCALITY => 'Dusseldorf',
                self::ELEM_LOCALITY => 'North Rhine-Westphalia',
                self::ELEM_POSTAL_CODE => '40545',
            ],
        ];
    }

    private function checkDusseldorfAssetsInGermanLang(Location $location)
    {
        // TODO should be realized assertation through Place entity
        // $this->assertEquals(51.2343, $location->getCoordinates()->getLatitude(), 'Latitude should be in Dusseldorf', 0.1);
        // $this->assertEquals(6.73134, $location->getCoordinates()->getLongitude(), 'Longitude should be in Dusseldorf', 0.1);
        $this->assertEquals('Düsseldorf', $location->getSubLocality());
        $this->assertEquals('Nordrhein-Westfalen', $location->getLocality());
        $this->assertEquals('Deutschland', $location->getCountry()->getName());
    }

    private function loadJsonCoordinates(Location $provider): bool
    {
        $success = true;
        $dirPath = __DIR__ . DIRECTORY_SEPARATOR .DIRECTORY_SEPARATOR;

        foreach (scandir($dirPath) as $file) {
            if (!is_file($dirPath.$file)) {
                continue;
            }
            $rawData = json_decode(file_get_contents($dirPath.$file), true);
            if (is_array($rawData)) {
                $provider->addPlace($this->mapRawDataToPlace($rawData));
                ++$this->countCoordFiles;
            } else {
                $success = false;
            }
        }

        return $success;
    }

    private function mapRawDataToPlace(array $rawData): Place
    {
        $root = $rawData['features'][0];

        $polygons = [];
        foreach ($root['geometry']['coordinates'] as $rawPolygon) {
            $tempPolygon = new Polygon();
            foreach ($rawPolygon as $coordinates) {
                $tempPolygon->addCoordinates(new Coordinates($coordinates[1], $coordinates[0]));
            }
            $polygons[] = $tempPolygon;
        }

        $addresses = [];
        foreach ($root['properties'] as $locale => $rawAddress) {
            $addresses[$locale] = $this->mapRawDataToAddress($rawAddress, $locale);
        }

        return new Place(
            $addresses,
            $polygons,
            Place::DEFAULT_LOCALE,
            $root['properties']['common']['postcode'],
            null,
            $rawData['geocoding']['attribution'],
            new Bounds(
                $root['properties']['common']['bbox'][0],
                $root['properties']['common']['bbox'][1],
                $root['properties']['common']['bbox'][2],
                $root['properties']['common']['bbox'][3]
            )
        );
    }

    private function mapRawDataToAddress(array $rawData, string $locale): Address
    {
        $adminLevels = [];
        foreach ($rawData['geocoding']['admin'] as $adminLevel => $name) {
            $level = (int) substr($adminLevel, 5);
            $adminLevels[$level] = new AdminLevel($level, $name);
        }

        return new Address(
            $locale,
            new AdminLevelCollection($adminLevels),
            $rawData['geocoding']['housenumber'] ?? '',
            $rawData['geocoding']['street'] ?? '',
            $rawData['geocoding']['state'] ?? '',
            $rawData['geocoding']['city'] ?? '',
            new Country($rawData['geocoding']['country'], $rawData['geocoding']['country_code'])
        );
    }
}
